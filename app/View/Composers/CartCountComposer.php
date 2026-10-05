<?php

namespace App\View\Composers;

use App\Services\Cart\Cart;
use Illuminate\View\View;

/**
 * Счётчик корзины в шапке сайта.
 */
class CartCountComposer
{
    public function __construct(private readonly Cart $cart) {}

    public function compose(View $view): void
    {
        $view->with('cartCount', $this->cart->count());
    }
}
