<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Посадочные страницы «раздел + производитель» (/category/akkumulyatori/titan):
 * в отличие от произвольных фильтров они индексируются. Страница есть только
 * у разделов из конфига и только если у производителя в разделе достаточно
 * товаров — иначе это тонкая страница без пользы для поиска.
 */
class BrandPages
{
    public function isAvailable(Category $category, Manufacturer $manufacturer): bool
    {
        return $this->isEnabledFor($category)
            && $this->productCount($category, $manufacturer) >= $this->minProducts();
    }

    /**
     * Производители раздела, у которых есть страница, по алфавиту.
     *
     * @return Collection<int, Manufacturer>
     */
    public function manufacturers(Category $category): Collection
    {
        $ids = $this->changedAtByManufacturer($category)->keys();

        return Manufacturer::query()->whereIn('id', $ids)->orderBy('name')->get();
    }

    /**
     * Все страницы производителей для sitemap: раздел, производитель и дата
     * последнего изменения их товаров.
     *
     * @return Collection<int, array{category: Category, manufacturer: Manufacturer, changed_at: ?Carbon}>
     */
    public function pages(): Collection
    {
        $categories = Category::query()
            ->where('status', true)
            ->whereIn('slug', config('shop.brand_pages.category_slugs'))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $categories->flatMap(function (Category $category): Collection {
            $changedAt = $this->changedAtByManufacturer($category);
            $manufacturers = Manufacturer::query()->whereIn('id', $changedAt->keys())->orderBy('name')->get();

            return $manufacturers->map(fn (Manufacturer $manufacturer): array => [
                'category' => $category,
                'manufacturer' => $manufacturer,
                'changed_at' => $changedAt->get($manufacturer->id),
            ]);
        })->values();
    }

    private function isEnabledFor(Category $category): bool
    {
        return in_array($category->slug, config('shop.brand_pages.category_slugs'), true);
    }

    private function minProducts(): int
    {
        return (int) config('shop.brand_pages.min_products');
    }

    private function productCount(Category $category, Manufacturer $manufacturer): int
    {
        return Product::query()
            ->where('status', true)
            ->where('manufacturer_id', $manufacturer->id)
            ->inCategoryTree($category)
            ->count();
    }

    /**
     * id производителя → дата изменения его свежего товара в разделе, только
     * для тех, у кого товаров достаточно для страницы.
     *
     * @return Collection<int, ?Carbon>
     */
    private function changedAtByManufacturer(Category $category): Collection
    {
        if (! $this->isEnabledFor($category)) {
            return collect();
        }

        return Product::query()
            ->where('status', true)
            ->whereNotNull('manufacturer_id')
            ->inCategoryTree($category)
            ->groupBy('manufacturer_id')
            ->havingRaw('count(*) >= ?', [$this->minProducts()])
            ->select('manufacturer_id', DB::raw('max(updated_at) as changed_at'))
            ->get()
            ->mapWithKeys(fn (Product $row): array => [$row->manufacturer_id => $row->changed_at === null ? null : Carbon::parse($row->changed_at)]);
    }
}
