<?php

namespace App\View\Composers;

use App\Services\SavedProducts\Compare;
use App\Services\SavedProducts\Wishlist;
use Illuminate\View\View;

/**
 * Счётчики закладок и сравнения в шапке.
 */
class SavedProductsCountComposer
{
    public function __construct(
        private readonly Wishlist $wishlist,
        private readonly Compare $compare,
    ) {}

    public function compose(View $view): void
    {
        $view->with([
            'wishlistCount' => $this->wishlist->count(),
            'compareCount' => $this->compare->count(),
        ]);
    }
}
