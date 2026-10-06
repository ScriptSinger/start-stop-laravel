<?php

namespace App\View\Composers;

use App\Services\SavedProducts\Compare;
use App\Services\SavedProducts\Wishlist;
use Illuminate\View\View;

/**
 * Счётчики закладок и сравнения в шапке и id товаров в них (для отметки
 * кнопок на карточках).
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
            'wishlistIds' => $this->wishlist->productIds(),
            'compareIds' => $this->compare->productIds(),
        ]);
    }
}
