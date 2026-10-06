<?php

namespace App\Http\Controllers;

use App\Actions\PlaceOrder;
use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Services\Cart\Cart;
use Artesaos\SEOTools\Facades\SEOTools;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    /**
     * Оформление — на странице корзины, как на старом сайте; старый адрес ведёт туда.
     */
    public function create(): RedirectResponse
    {
        return redirect()->route('cart.index');
    }

    public function store(CheckoutRequest $request, Cart $cart, PlaceOrder $placeOrder): RedirectResponse
    {
        $lines = $cart->lines();

        if ($lines->isEmpty()) {
            return redirect()->route('cart.index')
                ->with('status', 'Корзина пуста — возможно, товары сняли с продажи.');
        }

        $order = $placeOrder->handle($lines, $request->delivery(), $request->payment(), $request->contact(), $request->user());

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

        SEOTools::setTitle("Ваш заказ #{$order->id} сформирован!");

        return view('checkout-success', ['order' => $order]);
    }
}
