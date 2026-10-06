<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Artesaos\SEOTools\Facades\SEOTools;
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
        SEOTools::setTitle('Личный кабинет');

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

        SEOTools::setTitle('История заказов');

        return view('account.orders', ['orders' => $orders]);
    }

    public function order(Request $request, int $order): View
    {
        $order = $request->user()->orders()->visibleToCustomer()->with('items.product')->findOrFail($order);

        SEOTools::setTitle("Заказ #{$order->id}");

        return view('account.order', ['order' => $order]);
    }
}
