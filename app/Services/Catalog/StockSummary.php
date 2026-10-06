<?php

namespace App\Services\Catalog;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Сводка остатков по активным товарам: сколько позиций в наличии, под заказ
 * и нет совсем, сколько штук на складе и на какую сумму (по обычной цене).
 * «Под заказ» — как на витрине: своего нет, у поставщика не меньше порога.
 */
class StockSummary
{
    /**
     * @return array{total: int, in_stock: int, units: int, on_order: int, out_of_stock: int, stock_value: float}
     */
    public function overall(): array
    {
        return $this->normalize(
            $this->aggregate(DB::table('products'))->where('products.status', true)->first(),
        );
    }

    /**
     * @return Collection<int, array{id: int|null, name: string, total: int, in_stock: int, units: int, on_order: int, out_of_stock: int, stock_value: float}>
     */
    public function byManufacturer(): Collection
    {
        $rows = $this->aggregate(
            DB::table('products')
                ->leftJoin('manufacturers', 'manufacturers.id', '=', 'products.manufacturer_id')
                ->where('products.status', true)
                ->groupBy('products.manufacturer_id', 'manufacturers.name')
                ->addSelect('products.manufacturer_id as id', 'manufacturers.name'),
        )->get();

        return $this->rows($rows, 'Без производителя');
    }

    /**
     * Товар может лежать в нескольких категориях — тогда он посчитан в каждой.
     *
     * @return Collection<int, array{id: int|null, name: string, total: int, in_stock: int, units: int, on_order: int, out_of_stock: int, stock_value: float}>
     */
    public function byCategory(): Collection
    {
        $rows = $this->aggregate(
            DB::table('products')
                ->join('category_product', 'category_product.product_id', '=', 'products.id')
                ->join('categories', 'categories.id', '=', 'category_product.category_id')
                ->where('products.status', true)
                ->groupBy('categories.id', 'categories.name')
                ->addSelect('categories.id', 'categories.name'),
        )->get();

        return $this->rows($rows, 'Без категории');
    }

    private function aggregate(Builder $query): Builder
    {
        $minimum = (int) config('shop.supplier_order_min_quantity');

        return $query->selectRaw(
            'count(*) as total,
            sum(case when products.quantity > 0 then 1 else 0 end) as in_stock,
            sum(case when products.quantity > 0 then products.quantity else 0 end) as units,
            sum(case when products.quantity <= 0 and products.supplier_quantity >= ? then 1 else 0 end) as on_order,
            sum(case when products.quantity > 0 then products.quantity * products.price else 0 end) as stock_value',
            [$minimum],
        );
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, array{id: int|null, name: string, total: int, in_stock: int, units: int, on_order: int, out_of_stock: int, stock_value: float}>
     */
    private function rows(Collection $rows, string $emptyName): Collection
    {
        return $rows
            ->map(fn (object $row): array => [
                'id' => $row->id === null ? null : (int) $row->id,
                'name' => $row->name ?? $emptyName,
                ...$this->normalize($row),
            ])
            ->sortBy([['units', 'desc'], ['total', 'desc'], ['name', 'asc']])
            ->values();
    }

    /**
     * @return array{total: int, in_stock: int, units: int, on_order: int, out_of_stock: int, stock_value: float}
     */
    private function normalize(?object $row): array
    {
        $total = (int) ($row->total ?? 0);
        $inStock = (int) ($row->in_stock ?? 0);
        $onOrder = (int) ($row->on_order ?? 0);

        return [
            'total' => $total,
            'in_stock' => $inStock,
            'units' => (int) ($row->units ?? 0),
            'on_order' => $onOrder,
            'out_of_stock' => $total - $inStock - $onOrder,
            'stock_value' => round((float) ($row->stock_value ?? 0), 2),
        ];
    }
}
