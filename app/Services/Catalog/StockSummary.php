<?php

namespace App\Services\Catalog;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Сводка остатков по активным товарам: сколько позиций в наличии, под заказ
 * и нет совсем.
 * «Под заказ» — как на витрине: своего нет, у поставщика не меньше порога.
 */
class StockSummary
{
    /**
     * @param  list<int>|null  $categoryIds
     * @return array{total: int, in_stock: int, on_order: int, out_of_stock: int}
     */
    public function overall(?array $categoryIds = null): array
    {
        return $this->normalize(
            $this->aggregate(DB::table('products'))
                ->where('products.status', true)
                ->whereNull('products.deleted_at')
                ->when($categoryIds !== null, fn (Builder $query) => $query->whereExists(fn (Builder $categories) => $categories
                    ->selectRaw('1')
                    ->from('category_product')
                    ->whereColumn('category_product.product_id', 'products.id')
                    ->whereIn('category_product.category_id', $categoryIds)))
                ->first(),
        );
    }

    private function aggregate(Builder $query): Builder
    {
        $minimum = (int) config('shop.supplier_order_min_quantity');

        return $query->selectRaw(
            'count(*) as total,
            sum(case when products.quantity > 0 then 1 else 0 end) as in_stock,
            sum(case when products.quantity <= 0 and products.supplier_quantity >= ? then 1 else 0 end) as on_order',
            [$minimum],
        );
    }

    /**
     * @return array{total: int, in_stock: int, on_order: int, out_of_stock: int}
     */
    private function normalize(?object $row): array
    {
        $total = (int) ($row->total ?? 0);
        $inStock = (int) ($row->in_stock ?? 0);
        $onOrder = (int) ($row->on_order ?? 0);

        return [
            'total' => $total,
            'in_stock' => $inStock,
            'on_order' => $onOrder,
            'out_of_stock' => $total - $inStock - $onOrder,
        ];
    }
}
