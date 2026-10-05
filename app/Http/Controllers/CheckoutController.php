<?php

namespace App\Http\Controllers;

use App\Actions\PlaceOrder;
use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Services\Cart\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function create(Cart $cart): View|RedirectResponse
    {
        $lines = $cart->lines();

        if ($lines->isEmpty()) {
            return redirect()->route('cart.index');
        }

        return view('checkout', [
            'lines' => $lines,
            'total' => $cart->total($lines),
            'isPickupOnly' => $cart->isPickupOnly($lines),
        ]);
    }

    public function store(CheckoutRequest $request, Cart $cart, PlaceOrder $placeOrder): RedirectResponse
    {
        $lines = $cart->lines();

        if ($lines->isEmpty()) {
            return redirect()->route('cart.index')
                ->with('status', 'Корзина пуста — возможно, товары сняли с продажи.');
        }

        $order = $placeOrder->handle($lines, $request->delivery(), $request->payment(), $request->contact());

        $cart->clear();
        // Не флеш: страница «Спасибо» должна пережить обновление.
        session(['placed_order_id' => $order->id]);

        return redirect()->route('checkout.success');
    }

    /**
     * Номер заказа — из сессии, а не из адреса: иначе по ссылкам
     * /checkout/success/1, /2… можно было бы смотреть чужие заказы.
     */
    public function success(): View|RedirectResponse
    {
        $order = Order::query()->with('items')->find(session('placed_order_id'));

        if (! $order) {
            return redirect()->route('home');
        }

        return view('checkout-success', ['order' => $order]);
    }
}
