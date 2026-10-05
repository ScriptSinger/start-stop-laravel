<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Cart\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Cart $cart): View
    {
        $lines = $cart->lines();

        return view('cart', [
            'lines' => $lines,
            'total' => $cart->total($lines),
            'isPickupOnly' => $cart->isPickupOnly($lines),
        ]);
    }

    public function store(Request $request, Cart $cart, Product $product): RedirectResponse
    {
        abort_unless($product->status, 404);

        $cart->add(
            $product,
            $this->quantity($request),
            $request->boolean('trade_in'),
        );

        return redirect()
            ->route('cart.index')
            ->with('status', "«{$product->name}» добавлен в корзину.");
    }

    public function update(Request $request, Cart $cart, Product $product): RedirectResponse
    {
        $cart->update($product, $this->quantity($request, min: 0), $request->boolean('trade_in'));

        return redirect()->route('cart.index');
    }

    public function destroy(Cart $cart, Product $product): RedirectResponse
    {
        $cart->remove($product);

        return redirect()->route('cart.index');
    }

    private function quantity(Request $request, int $min = 1): int
    {
        $quantity = filter_var($request->input('quantity', 1), FILTER_VALIDATE_INT);

        // Мусор в поле — 1, а не удаление: ноль удаляет только когда введён явно.
        return $quantity === false ? 1 : max($min, min($quantity, Cart::MAX_QUANTITY));
    }
}
