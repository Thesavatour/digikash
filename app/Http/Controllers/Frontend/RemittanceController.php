<?php

namespace App\Http\Controllers\Frontend;

use App\Constants\CurrencyRole;
use App\Enums\PayoutMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Remittance\ConfirmTransferRequest;
use App\Http\Requests\Remittance\QuoteRequest;
use App\Models\Beneficiary;
use App\Models\RemittanceCorridor;
use App\Models\RemittanceQuote;
use App\Models\RemittanceTransfer;
use App\Services\Remittance\PayoutRouter;
use App\Services\Remittance\QuoteService;
use App\Services\Remittance\RemittanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class RemittanceController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $transfers = RemittanceTransfer::forUser($request->user()->id)
            ->with(['beneficiary', 'corridor'])
            ->when($search !== '', function ($q) use ($search): void {
                $term = '%'.$search.'%';
                $q->where(function ($q) use ($term): void {
                    $q->where('reference', 'like', $term)
                        ->orWhereHas('beneficiary', fn ($b) => $b->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('country_code', 'like', $term));
                });
            })
            ->when($status !== '' && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('frontend.user.remittance.index', compact('transfers', 'search', 'status'));
    }

    public function create(Request $request): View
    {
        $corridors     = RemittanceCorridor::active()->get();
        $beneficiaries = Beneficiary::forUser($request->user()->id)->get();
        $wallets       = $request->user()
            ->wallets()
            ->with(['currency.roles'])
            ->active(CurrencyRole::SENDER)
            ->get();

        $preselectedBeneficiary = $request->filled('beneficiary_id')
            ? Beneficiary::forUser($request->user()->id)->find($request->integer('beneficiary_id'))
            : null;

        $suggestedCorridor = $preselectedBeneficiary
            ? $corridors->first(fn ($corridor) => strtoupper($corridor->destination_currency) === strtoupper($preselectedBeneficiary->currency))
            : null;

        $primaryWallet = $wallets->first();

        return view('frontend.user.remittance.create', compact('corridors', 'beneficiaries', 'wallets', 'preselectedBeneficiary', 'suggestedCorridor', 'primaryWallet'));
    }

    public function quote(QuoteRequest $request, QuoteService $quoteService, PayoutRouter $router): RedirectResponse
    {
        try {
            $corridor = $router->findActiveById((int) $request->corridor_id);

            $quote = $quoteService->quote(
                user: $request->user(),
                corridor: $corridor,
                sendAmount: (float) $request->send_amount,
                payoutMethod: PayoutMethod::from($request->payout_method),
                beneficiaryId: $request->filled('beneficiary_id') ? (int) $request->beneficiary_id : null,
            );

            return redirect()->route('user.remittance.review', $quote->quote_id);
        } catch (Throwable $e) {
            report($e);
            notifyEvs('error', $e->getMessage());

            return back()->withInput();
        }
    }

    public function review(Request $request, string $quoteId): View|RedirectResponse
    {
        $quote = RemittanceQuote::with(['corridor', 'beneficiary'])
            ->where('quote_id', $quoteId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if (! $quote->isUsable()) {
            notifyEvs('error', __('Quote expired. Please request a new quote.'));

            return redirect()->route('user.remittance.create');
        }

        $beneficiaries = Beneficiary::forUser($request->user()->id)
            ->where('currency', $quote->destination_currency)
            ->get();

        $wallets = $request->user()
            ->wallets()
            ->with(['currency.roles'])
            ->active(CurrencyRole::SENDER)
            ->whereHas('currency', fn ($q) => $q->where('code', $quote->source_currency))
            ->get();

        return view('frontend.user.remittance.review', compact('quote', 'beneficiaries', 'wallets'));
    }

    public function confirm(ConfirmTransferRequest $request, RemittanceService $remittance): RedirectResponse
    {
        try {
            $quote       = RemittanceQuote::where('quote_id', $request->quote_id)->firstOrFail();
            $beneficiary = Beneficiary::findOrFail($request->beneficiary_id);

            $transfer = $remittance->send(
                user: $request->user(),
                quote: $quote,
                beneficiary: $beneficiary,
                context: [
                    'wallet_id'       => (int) $request->wallet_id,
                    'purpose'         => $request->purpose,
                    'source_of_funds' => $request->source_of_funds,
                ],
            );

            notifyEvs('success', __('Transfer submitted successfully.'));

            return redirect()->route('user.remittance.show', $transfer->reference);
        } catch (Throwable $e) {
            report($e);
            notifyEvs('error', $e->getMessage());

            return back()->withInput();
        }
    }

    public function show(Request $request, string $reference): View
    {
        $transfer = RemittanceTransfer::with(['beneficiary', 'corridor', 'transaction'])
            ->forUser($request->user()->id)
            ->where('reference', $reference)
            ->firstOrFail();

        return view('frontend.user.remittance.show', compact('transfer'));
    }
}
