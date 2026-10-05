<?php

namespace App\Http\Requests;

use App\Models\Category;

/**
 * Поиск по каталогу: ?search=TITAN&category_id=1 плюс сортировка и
 * «товаров на странице» — как в каталоге.
 */
class SearchRequest extends CatalogFilterRequest
{
    public function search(): string
    {
        return $this->string('search')->squish()->limit(100, '')->toString();
    }

    /**
     * Неизвестная или выключенная категория — поиск по всему каталогу.
     */
    public function category(): ?Category
    {
        $categoryId = $this->integer('category_id');

        return $categoryId > 0
            ? Category::query()->where('status', true)->find($categoryId)
            : null;
    }
}
