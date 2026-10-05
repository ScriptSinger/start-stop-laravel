<?php

namespace App\Services\SavedProducts;

/**
 * Закладки покупателя.
 */
class Wishlist extends SessionProductList
{
    protected function sessionKey(): string
    {
        return 'wishlist';
    }
}
