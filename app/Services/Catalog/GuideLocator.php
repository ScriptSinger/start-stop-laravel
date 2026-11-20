<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Guide;
use App\Models\Product;

/**
 * Какой гайд показывать на странице раздела или товара: для аккумуляторов —
 * «Как выбрать аккумулятор» (shop.guides.battery). Обычные ссылки в HTML
 * передают гайду вес страниц каталога и ведут покупателя к выбору.
 */
class GuideLocator
{
    public function forCategory(Category $category): ?Guide
    {
        return $this->isBatteryCategory([$category->getKey()]) ? $this->battery() : null;
    }

    /**
     * Нужна загруженная связь categories.
     */
    public function forProduct(Product $product): ?Guide
    {
        return $this->isBatteryCategory($product->categories->modelKeys()) ? $this->battery() : null;
    }

    /**
     * @param  array<int, int|string>  $categoryIds
     */
    private function isBatteryCategory(array $categoryIds): bool
    {
        return array_intersect(array_map('intval', $categoryIds), config('shop.guides.battery.category_ids')) !== [];
    }

    private function battery(): ?Guide
    {
        return Guide::query()->active()->where('slug', config('shop.guides.battery.slug'))->first();
    }
}
