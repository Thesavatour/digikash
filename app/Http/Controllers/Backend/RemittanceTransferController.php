<?php

namespace App\Http\Controllers\Backend;

use App\Enums\RemittanceStatus;
use App\Enums\TrxStatus;
use App\Models\RemittanceTransfer;
use App\Services\Payment\Payout\PayoutResult;
use App\Services\Remittance\RemittanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class RemittanceTransferController extends BaseController
{
    public function __construct(protected RemittanceService $remittance) {}

    public static function permissions(): array
    {
        return [
            'index|show'          => 'remittance-transfer-list',
            'approve|fail|refund' => 'remittance-transfer-manage',
        ];
    }

    public function index(Request $request): View
    {
        $query = RemittanceTransfer::query()
            ->with(['user', 'beneficiary', 'corridor'])
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')),
            )
            ->when(
                $request->filled('search'),
                fn ($q) => $q->where(function ($q) use ($request) {
                    $term = '%'.$request->string('search').'%';
                    $q->where('reference', 'like', $term)
                        ->orWhere('gateway_reference', 'like', $term)
                        ->orWhereHas('user', fn ($u) => $u->where('email', 'like', $term)
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$term]));
                }),
            )
            ->latest();

        $transfers = $query->paginate(20)->withQueryString();
        $statuses  = RemittanceStatus::options();

        return view('backend.remittance.transfers.index', compact('transfers', 'statuses'));
    }

    public function show(RemittanceTransfer $transfer): View
    {
        $transfer->load(['user', 'beneficiary', 'corridor', 'transaction', 'quote']);

        return view('backend.remittance.transfers.show', compact('transfer'));
    }

    public function approve(RemittanceTransfer $transfer): RedirectResponse
    {
        if (! $this->isApprovable($transfer)) {
            notifyEvs('error', __('This transfer can no longer be approved (status: :status).', ['status' => $transfer->status->label()]));

            return back();
        }

        try {
            $result = new PayoutResult(
                status: RemittanceStatus::Completed,
                gatewayReference: $transfer->gateway_reference ?? 'MANUAL-APPROVED',
                message: __('Manually approved by admin.'),
            );

            $transfer->update(['gateway_payload' => $result->payload]);
            $transfer->recordStatus(RemittanceStatus::Completed, $result->message);
            $this->syncTransaction($transfer, $result);

            notifyEvs('success', __('Transfer marked as completed.'));
        } catch (Throwable $e) {
            report($e);
            notifyEvs('error', $e->getMessage());
        }

        return back();
    }

    public function fail(Request $request, RemittanceTransfer $transfer): RedirectResponse
    {
        if (! $this->isFailable($transfer)) {
            notifyEvs('error', __('This transfer can no longer be marked failed (status: :status).', ['status' => $transfer->status->label()]));

            return back();
        }

        $reason = trim((string) $request->input('reason')) ?: __('Marked failed by admin.');

        $result = new PayoutResult(
            status: RemittanceStatus::Failed,
            message: $reason,
        );

        $transfer->recordStatus(RemittanceStatus::Failed, $reason);
        $this->syncTransaction($transfer, $result);

        notifyEvs('success', __('Transfer marked as failed.'));

        return back();
    }

    public function refund(Request $request, RemittanceTransfer $transfer): RedirectResponse
    {
        try {
            $this->remittance->refund($transfer, $request->input('reason'));

            notifyEvs('success', __('Transfer refunded to sender wallet.'));
        } catch (Throwable $e) {
            report($e);
            notifyEvs('error', $e->getMessage());
        }

        return back();
    }

    /**
     * Approve is allowed only while the transfer is still in flight.
     * Terminal states (completed/failed/refunded/canceled) block the action.
     */
    protected function isApprovable(RemittanceTransfer $transfer): bool
    {
        return in_array($transfer->status, [
            RemittanceStatus::Pending,
            RemittanceStatus::Submitted,
            RemittanceStatus::Processing,
        ], true);
    }

    /**
     * Fail is allowed only while the transfer is still in flight or refundable.
     */
    protected function isFailable(RemittanceTransfer $transfer): bool
    {
        return ! in_array($transfer->status, [
            RemittanceStatus::Completed,
            RemittanceStatus::Refunded,
            RemittanceStatus::Canceled,
            RemittanceStatus::Failed,
        ], true);
    }

    /**
     * Mirror the orchestrator's reconciliation step so manual admin actions
     * keep the linked Transaction record in sync with the transfer state.
     */
    protected function syncTransaction(RemittanceTransfer $transfer, PayoutResult $result): void
    {
        $transaction = $transfer->fresh()->transaction;

        if (! $transaction) {
            return;
        }

        if ($result->status === RemittanceStatus::Completed) {
            $transaction->update(['status' => TrxStatus::COMPLETED]);
        }

        if ($result->status === RemittanceStatus::Failed) {
            $transaction->update([
                'status'  => TrxStatus::FAILED,
                'remarks' => $result->message,
            ]);
        }
    }
}
