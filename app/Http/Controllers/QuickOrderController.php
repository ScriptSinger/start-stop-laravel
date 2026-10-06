<?php

namespace App\Http\Controllers;

use App\Actions\PlaceOrder;
use App\Http\Requests\QuickOrderRequest;
use App\Models\Product;
use App\Services\Cart\CartLine;
use Artesaos\SEOTools\Facades\SEOTools;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «Быстрый заказ» одного товара: имя и телефон, без корзины. Способ
 * получения и оплаты менеджер уточнит, когда перезвонит.
 */
class QuickOrderController extends Controller
{
    public function create(Request $request, Product $product): View
    {
        abort_unless($product->status, 404);

        SEOTools::setTitle('Быстрый заказ');

        return view($request->ajax() ? 'quick-order.form' : 'quick-order.page', ['product' => $product]);
    }

    public function store(QuickOrderRequest $request, Product $product, PlaceOrder $placeOrder): JsonResponse|RedirectResponse
    {
        abort_unless($product->status, 404);

        $order = $placeOrder->handle(
            collect([new CartLine($product, (int) ($request->validated('quantity') ?? 1), $request->boolean('trade_in'))]),
            delivery: null,
            payment: null,
            contact: [
                'name' => $request->validated('name'),
                'phone' => $request->validated('phone'),
                'email' => null,
                'address' => null,
                'comment' => trim('Быстрый заказ. '.$request->validated('comment')),
            ],
            customer: $request->user(),
        );

        session(['placed_order_id' => $order->id]);

        $message = "Спасибо! Заказ №{$order->id} принят — менеджер перезвонит, чтобы его подтвердить.";

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : redirect()->route('checkout.success');
    }
}
