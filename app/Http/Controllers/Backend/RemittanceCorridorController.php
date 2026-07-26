<?php

namespace App\Http\Controllers\Backend;

use App\Enums\PayoutMethod;
use App\Enums\RemittanceStatus;
use App\Http\Requests\Backend\RemittanceCorridorRequest;
use App\Models\Currency;
use App\Models\RemittanceCorridor;
use App\Models\RemittanceTransfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RemittanceCorridorController extends BaseController
{
    public static function permissions(): array
    {
        return [
            'index'                            => 'remittance-corridor-list',
            'create|store|edit|update|destroy' => 'remittance-corridor-manage',
        ];
    }

    public function index(Request $request): View
    {
        $search       = trim((string) $request->query('q', ''));
        $statusFilter = $request->query('status');

        $corridors = RemittanceCorridor::query()
            ->withCount('transfers')
            ->when($search !== '', function ($q) use ($search): void {
                $term = '%'.$search.'%';
                $q->where(function ($q) use ($term): void {
                    $q->where('name', 'like', $term)
                        ->orWhere('source_country', 'like', $term)
                        ->orWhere('source_currency', 'like', $term)
                        ->orWhere('destination_country', 'like', $term)
                        ->orWhere('destination_currency', 'like', $term)
                        ->orWhere('payout_gateway', 'like', $term);
                });
            })
            ->when($statusFilter === 'active', fn ($q) => $q->where('status', true))
            ->when($statusFilter === 'disabled', fn ($q) => $q->where('status', false))
            ->orderBy('source_country')
            ->orderBy('destination_country')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total'           => RemittanceCorridor::query()->count(),
            'active'          => RemittanceCorridor::query()->where('status', true)->count(),
            'transfers'       => RemittanceTransfer::query()->count(),
            'completed_value' => (float) RemittanceTransfer::query()
                ->where('status', RemittanceStatus::Completed)
                ->sum('send_amount'),
        ];

        return view('backend.remittance.corridors.index', compact('corridors', 'stats', 'search', 'statusFilter'));
    }

    public function create(): View
    {
        return view('backend.remittance.corridors.create', $this->formData(new RemittanceCorridor));
    }

    public function store(RemittanceCorridorRequest $request): RedirectResponse
    {
        RemittanceCorridor::query()->create($request->validated());

        notifyEvs('success', __('Remittance corridor created.'));

        return redirect()->route('admin.remittance.corridors.index');
    }

    public function edit(RemittanceCorridor $corridor): View
    {
        $corridor->loadCount('transfers');

        return view('backend.remittance.corridors.edit', $this->formData($corridor));
    }

    public function update(RemittanceCorridorRequest $request, RemittanceCorridor $corridor): RedirectResponse
    {
        $corridor->update($request->validated());

        notifyEvs('success', __('Remittance corridor updated.'));

        return redirect()->route('admin.remittance.corridors.index');
    }

    public function destroy(RemittanceCorridor $corridor): RedirectResponse
    {
        if ($corridor->transfers()->exists() || $corridor->quotes()->exists()) {
            notifyEvs('error', __('Cannot delete a corridor that already has transfers or active quotes. Disable it instead.'));

            return back();
        }

        $corridor->delete();

        notifyEvs('success', __('Remittance corridor removed.'));

        return redirect()->route('admin.remittance.corridors.index');
    }

    /**
     * Shared payload for create + edit corridor forms.
     *
     * @return array<string, mixed>
     */
    protected function formData(RemittanceCorridor $corridor): array
    {
        return [
            'corridor'      => $corridor,
            'payoutMethods' => PayoutMethod::cases(),
            'countries'     => collect(getCountries())
                ->sortBy('name')
                ->values()
                ->all(),
            'currencies' => Currency::query()
                ->orderBy('code')
                ->get(['code', 'name', 'symbol'])
                ->all(),
            'gateways' => [
                'manual'      => __('Manual (admin approval)'),
                'bitnob'      => 'Bitnob',
                'flutterwave' => 'Flutterwave',
                'paystack'    => 'Paystack',
            ],
        ];
    }
}
