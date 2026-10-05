<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * «Похожие товары» на странице товара — алгоритм темы UniShop2
 * (uni_related::getAutoRelated): товары тех же категорий, только в наличии;
 * сначала с id больше текущего по возрастанию id, а если не набралось —
 * любые другие из этих категорий.
 */
class SimilarProducts
{
    /**
     * @return Collection<int, Product>
     */
    public function for(Product $product, int $limit = 5): Collection
    {
        $categoryIds = $product->categories()->pluck('categories.id');

        if ($categoryIds->isEmpty()) {
            return new Collection;
        }

        $query = fn (): Builder => Product::query()
            ->where('status', true)
            ->where('quantity', '>', 0)
            ->whereKeyNot($product->getKey())
            ->whereHas('categories', fn (Builder $categories) => $categories->whereIn('categories.id', $categoryIds))
            ->withCardData()
            ->orderBy('id')
            ->limit($limit);

        $similar = $query()->where('id', '>', $product->getKey())->get();

        return $similar->count() < $limit ? $query()->get() : $similar;
    }
}
