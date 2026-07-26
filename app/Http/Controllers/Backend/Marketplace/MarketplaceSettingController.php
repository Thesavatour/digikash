<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend\Marketplace;

use App\Http\Controllers\Backend\BaseController;
use App\Http\Requests\Marketplace\MarketplaceSettingRequest;
use App\Models\MarketplaceSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MarketplaceSettingController extends BaseController
{
    public static function permissions(): array
    {
        return [
            'edit|update' => 'marketplace-manage',
        ];
    }

    public function edit(): View
    {
        $settings = MarketplaceSetting::query()->first() ?? MarketplaceSetting::query()->create([]);

        $commissionUser = $settings->commission_user_id
            ? User::query()->find($settings->commission_user_id)
            : null;

        return view('backend.marketplace.settings', compact('settings', 'commissionUser'));
    }

    public function update(MarketplaceSettingRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $settings = MarketplaceSetting::query()->first() ?? MarketplaceSetting::query()->create([]);

        $settings->update([
            'enabled'          => (bool) $validated['enabled'],
            'commission_pct'   => (float) $validated['commission_pct'],
            'commission_fixed' => (float) ($validated['commission_fixed'] ?? 0),
            'min_order_amount' => (float) ($validated['min_order_amount'] ?? 0),
            'max_order_amount' => ($validated['max_order_amount'] ?? null) !== null && $validated['max_order_amount'] !== ''
                ? (float) $validated['max_order_amount']
                : null,
            'auto_release'        => (bool) $validated['auto_release'],
            'payout_hold_minutes' => (int) ($validated['payout_hold_minutes'] ?? 0),
            'commission_user_id'  => $validated['commission_user_id'] ?? null,
        ]);

        return back()->with('notifyevs', ['type' => 'success', 'message' => __('Marketplace settings updated')]);
    }
}
