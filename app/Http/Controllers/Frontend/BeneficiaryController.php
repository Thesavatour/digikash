<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\PayoutMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Beneficiary\StoreBeneficiaryRequest;
use App\Http\Requests\Beneficiary\UpdateBeneficiaryRequest;
use App\Models\Beneficiary;
use App\Services\Remittance\BeneficiaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class BeneficiaryController extends Controller
{
    public function __construct(protected BeneficiaryService $service) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $beneficiaries = $this->service->listForUser($request->user(), search: $search);

        return view('frontend.user.remittance.beneficiaries.index', compact('beneficiaries', 'search'));
    }

    public function create(): View
    {
        $payoutMethods = PayoutMethod::cases();

        return view('frontend.user.remittance.beneficiaries.create', compact('payoutMethods'));
    }

    public function store(StoreBeneficiaryRequest $request): RedirectResponse
    {
        try {
            $this->service->create($request->user(), $request->validated());
            notifyEvs('success', __('Beneficiary saved.'));

            return redirect()->route('user.beneficiaries.index');
        } catch (Throwable $e) {
            report($e);
            notifyEvs('error', $e->getMessage());

            return back()->withInput();
        }
    }

    public function edit(Request $request, Beneficiary $beneficiary): View
    {
        abort_unless($beneficiary->user_id === $request->user()->id, 403);

        $payoutMethods = PayoutMethod::cases();

        return view('frontend.user.remittance.beneficiaries.edit', compact('beneficiary', 'payoutMethods'));
    }

    public function update(UpdateBeneficiaryRequest $request, Beneficiary $beneficiary): RedirectResponse
    {
        try {
            $this->service->update($beneficiary, $request->validated());
            notifyEvs('success', __('Beneficiary updated.'));

            return redirect()->route('user.beneficiaries.index');
        } catch (Throwable $e) {
            report($e);
            notifyEvs('error', $e->getMessage());

            return back()->withInput();
        }
    }

    public function destroy(Request $request, Beneficiary $beneficiary): RedirectResponse
    {
        abort_unless($beneficiary->user_id === $request->user()->id, 403);

        $this->service->delete($beneficiary);
        notifyEvs('success', __('Beneficiary removed.'));

        return redirect()->route('user.beneficiaries.index');
    }
}
