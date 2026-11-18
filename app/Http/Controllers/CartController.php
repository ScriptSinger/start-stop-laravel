<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Services\Cart\Cart;
use App\Services\Cart\CartLine;
use Artesaos\SEOTools\Facades\SEOTools;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function state(Cart $cart): JsonResponse
    {
        return $this->miniCart($cart)->header('Cache-Control', 'no-store');
    }

    public function index(Request $request, Cart $cart): View
    {
        $lines = $cart->lines();
        $unavailableCount = $cart->unavailableCount($lines);
        $priceChanged = $cart->hasPriceChanges($lines);
        if ($unavailableCount > 0) {
            $cart->discardUnavailable();
        }
        $cart->rememberPrices($lines);

        SEOTools::setTitle('Оформление заказа');

        return view('cart', [
            'lines' => $lines,
            'total' => $cart->total($lines),
            'isPickupOnly' => $cart->isPickupOnly($lines),
            'unavailableCount' => $unavailableCount,
            'priceChanged' => $priceChanged,
            'contact' => $this->checkoutContact($request->user()),
        ]);
    }

    /**
     * Контактные данные вошедшего покупателя для формы оформления: чтобы не
     * вводить заново то, что он уже указал при регистрации. Имя в профиле одной
     * строкой — делим по первому пробелу на имя и фамилию, как в форме.
     *
     * @return array{name: string, lastname: string, phone: string, email: string}
     */
    private function checkoutContact(?Authenticatable $user): array
    {
        if (! $user instanceof Customer) {
            return ['name' => '', 'lastname' => '', 'phone' => '', 'email' => ''];
        }

        [$name, $lastname] = array_pad(explode(' ', trim((string) $user->name), 2), 2, '');

        return [
            'name' => $name,
            'lastname' => trim($lastname),
            'phone' => (string) $user->phone,
            'email' => (string) $user->email,
        ];
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
        if (($request->has('expected_state') && ! is_string($request->input('expected_state')))
            || ! $cart->update($product, $this->quantity($request, min: 0), $request->boolean('trade_in'), $request->input('expected_state'))) {
            return $this->staleLine($request);
        }

        return $request->expectsJson() ? $this->miniCart($cart) : redirect()->route('cart.index');
    }

    public function destroy(Request $request, Cart $cart, Product $product): RedirectResponse|JsonResponse
    {
        if (($request->has('expected_state') && ! is_string($request->input('expected_state')))
            || ! $cart->remove($product, $request->input('expected_state'))) {
            return $this->staleLine($request);
        }

        return $request->expectsJson() ? $this->miniCart($cart) : redirect()->route('cart.index');
    }

    private function staleLine(Request $request): RedirectResponse|JsonResponse
    {
        $message = 'Корзина изменилась в другой вкладке. Проверьте количество и повторите действие.';

        return $request->expectsJson()
            ? response()->json(['message' => $message], 409)
            : redirect()->route('cart.index')->with('status', $message);
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
