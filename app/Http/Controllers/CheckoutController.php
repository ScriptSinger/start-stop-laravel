<?php

namespace App\Http\Controllers;

use App\Actions\PlaceOrder;
use App\Enums\DeliveryMethod;
use App\Http\Requests\CheckoutRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Services\Cart\Cart;
use Artesaos\SEOTools\Facades\SEOTools;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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
        return DB::transaction(function () use ($request, $cart, $placeOrder): RedirectResponse {
            if ($request->user() instanceof Customer) {
                Customer::query()->whereKey($request->user()->getKey())->lockForUpdate()->firstOrFail();
            }
            $lines = $cart->lines();

            if ($request->delivery() !== DeliveryMethod::Pickup && $cart->isPickupOnly($lines)) {
                return redirect()->route('cart.index')->withInput()
                    ->withErrors(['delivery' => 'В корзине есть товар, который можно забрать только самовывозом.']);
            }

            if ($cart->unavailableCount($lines) > 0) {
                return redirect()->route('cart.index')
                    ->withInput()
                    ->with('status', 'Некоторые товары больше недоступны. Проверьте состав корзины перед оформлением.');
            }

            if ($cart->hasPriceChanges($lines)) {
                return redirect()->route('cart.index')
                    ->withInput()
                    ->with('status', 'Цена товара изменилась. Проверьте сумму заказа перед оформлением.');
            }

            if ($lines->isEmpty()) {
                return redirect()->route('cart.index')
                    ->with('status', 'Корзина пуста — возможно, товары сняли с продажи.');
            }

            $order = $placeOrder->handle($lines, $request->delivery(), $request->payment(), $request->contact(), $request->user(), $request->validated('metrika_client_id'));

            $cart->clear();
            // Не флеш: страница «Спасибо» должна пережить обновление.
            session(['placed_order_id' => $order->id]);

            return redirect()->route('checkout.success');
        }, attempts: 3);
    }

    /**
     * Номер заказа — из сессии, а не из адреса: иначе по ссылкам
     * /checkout/success/1, /2… можно было бы смотреть чужие заказы.
     */
    public function success(): View|RedirectResponse
    {
        $order = Order::query()->with('items.product')->find(session('placed_order_id'));

        if (! $order) {
            return redirect()->route('home');
        }

        SEOTools::setTitle("Ваш заказ #{$order->id} сформирован!");

        return view('checkout-success', ['order' => $order]);
    }
}
