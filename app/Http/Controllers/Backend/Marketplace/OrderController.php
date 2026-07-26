<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend\Marketplace;

use App\Exceptions\NotifyErrorException;
use App\Http\Controllers\Backend\BaseController;
use App\Models\MarketplaceOrder;
use App\Services\Marketplace\MarketplaceOrderHandler;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends BaseController
{
    public static function permissions(): array
    {
        return [
            'index'  => 'marketplace-order-list',
            'refund' => 'marketplace-order-manage',
        ];
    }

    public function index(Request $request): View
    {
        $orders = $this->ordersQuery($request);

        return view('backend.marketplace.orders.index', compact('orders'));
    }

    public function refund(MarketplaceOrder $order, MarketplaceOrderHandler $handler): RedirectResponse
    {
        try {
            $handler->refund($order);
        } catch (NotifyErrorException $e) {
            return back()->with('notifyevs', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('notifyevs', ['type' => 'success', 'message' => __('Order refunded')]);
    }

    private function ordersQuery(Request $request): LengthAwarePaginator
    {
        $status = $request->string('status')->toString();

        return MarketplaceOrder::query()
            ->with(['buyer:id,name,email', 'vendor:id,user_id,display_name', 'currency:id,code'])
            ->when(in_array($status, ['pending', 'released', 'refunded', 'cancelled'], true), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }
}
