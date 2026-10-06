<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Личный кабинет покупателя — адреса как на старом сайте: /my-account,
 * /order-history. Заказ ищется только среди заказов самого покупателя.
 */
class AccountController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.index', ['customer' => $request->user()]);
    }

    public function orders(Request $request): View
    {
        $orders = $request->user()->orders()
            ->visibleToCustomer()
            ->withCount('items')
            ->latest()
            ->orderByDesc('id')
            ->paginate(10);

        return view('account.orders', ['orders' => $orders]);
    }

    public function order(Request $request, int $order): View
    {
        $order = $request->user()->orders()->visibleToCustomer()->with('items.product')->findOrFail($order);

        return view('account.order', ['order' => $order]);
    }
}
