<?php

namespace App\Services\Catalog;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Фото товаров: сколько с фото и сколько без — отдельно у активных и неактивных.
 * Фото нет, если products.image пуст или это заглушка no_image.png.
 * Удалённые товары не считаются.
 */
class PhotoSummary
{
    /**
     * @param  list<int>|null  $categoryIds
     * @return array{with_photo: int, active_without_photo: int, inactive_without_photo: int}
     */
    public function overall(?array $categoryIds = null): array
    {
        $noPhoto = "(products.image is null or products.image in ('', 'no_image.png'))";

        $row = DB::table('products')
            ->whereNull('products.deleted_at')
            ->when($categoryIds !== null, fn (Builder $query) => $query->whereExists(fn (Builder $categories) => $categories
                ->selectRaw('1')
                ->from('category_product')
                ->whereColumn('category_product.product_id', 'products.id')
                ->whereIn('category_product.category_id', $categoryIds)))
            ->selectRaw(
                "sum(case when not {$noPhoto} then 1 else 0 end) as with_photo,
                sum(case when {$noPhoto} and products.status = 1 then 1 else 0 end) as active_without_photo,
                sum(case when {$noPhoto} and products.status = 0 then 1 else 0 end) as inactive_without_photo"
            )
            ->first();

        return [
            'with_photo' => (int) ($row->with_photo ?? 0),
            'active_without_photo' => (int) ($row->active_without_photo ?? 0),
            'inactive_without_photo' => (int) ($row->inactive_without_photo ?? 0),
        ];
    }
}
