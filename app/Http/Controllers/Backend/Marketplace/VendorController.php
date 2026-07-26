<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend\Marketplace;

use App\Enums\Marketplace\VendorStatus;
use App\Http\Controllers\Backend\BaseController;
use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorController extends BaseController
{
    public static function permissions(): array
    {
        return [
            'index'           => 'marketplace-vendor-list',
            'approve|suspend' => 'marketplace-vendor-manage',
        ];
    }

    public function index(Request $request): View
    {
        $vendors = $this->vendorsQuery($request);

        return view('backend.marketplace.vendors.index', compact('vendors'));
    }

    public function approve(Vendor $vendor): RedirectResponse
    {
        $vendor->update([
            'status'      => VendorStatus::ACTIVE,
            'approved_at' => $vendor->approved_at ?? now(),
        ]);

        return back()->with('notifyevs', ['type' => 'success', 'message' => __('Vendor approved')]);
    }

    public function suspend(Vendor $vendor): RedirectResponse
    {
        $vendor->update(['status' => VendorStatus::SUSPENDED]);

        return back()->with('notifyevs', ['type' => 'success', 'message' => __('Vendor suspended')]);
    }

    private function vendorsQuery(Request $request): LengthAwarePaginator
    {
        $status = $request->string('status')->toString();

        return Vendor::query()
            ->with('user:id,name,email')
            ->when(in_array($status, ['pending', 'active', 'suspended'], true), fn ($q) => $q->where('status', $status))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->toString();
                $q->where(fn ($inner) => $inner
                    ->where('display_name', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%")));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }
}
