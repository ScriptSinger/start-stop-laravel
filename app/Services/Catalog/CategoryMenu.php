<?php

namespace App\Services\Catalog;

use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Меню категорий в шапке (как menu1 в UniShop2): разделы с иконками и
 * выпадающий второй уровень.
 *
 * Второй уровень — подкатегории раздела, а если их нет — производители его
 * товаров. На старом сайте бренды АКБ были подкатегориями («Аккумуляторы →
 * TITAN»); у нас это производители, поэтому ссылка ведёт на раздел с
 * фильтром ?manufacturer[]=…
 */
class CategoryMenu
{
    /** Меньше двух производителей — выпадающий список не нужен. */
    private const MIN_MANUFACTURERS = 2;

    /**
     * @return Collection<int, array{category: Category, children: list<array{title: string, url: string}>}>
     */
    public function items(): Collection
    {
        $roots = Category::query()
            ->whereNull('parent_id')
            ->where('status', true)
            ->with(['children' => fn ($query) => $query->where('status', true)->orderBy('sort_order')->orderBy('name')])
            // Как в OpenCart: при равном sort_order — по названию.
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $manufacturers = $this->manufacturersByCategory($roots->modelKeys());

        return $roots->map(function (Category $category) use ($manufacturers): array {
            $children = $category->children->isNotEmpty()
                ? $category->children->map(fn (Category $child): array => [
                    'title' => $child->name,
                    'url' => route('category.show', $child),
                ])->all()
                : collect($manufacturers->get($category->id, []))
                    ->map(fn (object $manufacturer): array => [
                        'title' => $manufacturer->name,
                        'url' => route('category.show', ['category' => $category, 'manufacturer' => [$manufacturer->id]]),
                    ])->all();

            return [
                'category' => $category,
                'children' => count($children) >= self::MIN_MANUFACTURERS || $category->children->isNotEmpty() ? $children : [],
            ];
        });
    }

    /**
     * Производители активных товаров по разделам — одним запросом.
     *
     * @param  list<int>  $categoryIds
     * @return Collection<int, Collection<int, object{id: int, name: string, category_id: int}>>
     */
    private function manufacturersByCategory(array $categoryIds): Collection
    {
        return DB::table('category_product')
            ->join('products', 'products.id', '=', 'category_product.product_id')
            ->join('manufacturers', 'manufacturers.id', '=', 'products.manufacturer_id')
            ->whereIn('category_product.category_id', $categoryIds)
            ->where('products.status', true)
            ->distinct()
            ->orderBy('manufacturers.name')
            ->get(['category_product.category_id', 'manufacturers.id', 'manufacturers.name'])
            ->groupBy('category_id');
    }
}
