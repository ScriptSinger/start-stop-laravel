<?php

namespace App\View\Composers;

use App\Services\Cart\Cart;
use App\Services\Cart\CartLine;
use Illuminate\View\View;

/**
 * Корзина в шапке: счётчик и мини-корзина (окно «Корзина»).
 */
class HeaderCartComposer
{
    public function __construct(private readonly Cart $cart) {}

    public function compose(View $view): void
    {
        $lines = $this->cart->lines();

        $view->with([
            'cartCount' => $lines->sum(fn (CartLine $line): int => $line->quantity),
            'cartLines' => $lines,
            'cartTotal' => $this->cart->total($lines),
        ]);
    }
}
