<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Cart\Cart;
use App\Services\Cart\CartLine;
use Artesaos\SEOTools\Facades\SEOTools;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Cart $cart): View
    {
        $lines = $cart->lines();

        SEOTools::setTitle('Оформление заказа');

        return view('cart', [
            'lines' => $lines,
            'total' => $cart->total($lines),
            'isPickupOnly' => $cart->isPickupOnly($lines),
        ]);
    }

    /**
     * Из каталога — без перехода: на ajax-запрос отвечаем мини-корзиной для
     * окна «Корзина», как на старом сайте. Без JavaScript — переход в корзину.
     */
    public function store(Request $request, Cart $cart, Product $product): RedirectResponse|JsonResponse
    {
        abort_unless($product->status, 404);

        $cart->add(
            $product,
            $this->quantity($request),
            $request->boolean('trade_in'),
        );

        if ($request->expectsJson()) {
            return $this->miniCart($cart);
        }

        return redirect()
            ->route('cart.index')
            ->with('status', "«{$product->name}» добавлен в корзину.");
    }

    public function update(Request $request, Cart $cart, Product $product): RedirectResponse|JsonResponse
    {
        $cart->update($product, $this->quantity($request, min: 0), $request->boolean('trade_in'));

        return $request->expectsJson() ? $this->miniCart($cart) : redirect()->route('cart.index');
    }

    public function destroy(Request $request, Cart $cart, Product $product): RedirectResponse|JsonResponse
    {
        $cart->remove($product);

        return $request->expectsJson() ? $this->miniCart($cart) : redirect()->route('cart.index');
    }

    /**
     * Счётчик и разметка мини-корзины — для шапки и окна «Корзина».
     */
    private function miniCart(Cart $cart): JsonResponse
    {
        $lines = $cart->lines();

        return response()->json([
            'count' => $lines->sum(fn (CartLine $line): int => $line->quantity),
            'products' => $lines->map(fn (CartLine $line): int => $line->product->id)->values(),
            'html' => view('partials.mini-cart', ['cartLines' => $lines, 'cartTotal' => $cart->total($lines)])->render(),
        ]);
    }

    private function quantity(Request $request, int $min = 1): int
    {
        $quantity = filter_var($request->input('quantity', 1), FILTER_VALIDATE_INT);

        // Мусор в поле — 1, а не удаление: ноль удаляет только когда введён явно.
        return $quantity === false ? 1 : max($min, min($quantity, Cart::MAX_QUANTITY));
    }
}
