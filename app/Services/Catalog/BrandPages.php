<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Страницы «раздел + производитель» (/category/akkumulyatori/titan).
 *
 * Адрес красивый у любого производителя, у которого в разделе есть товар, —
 * им пользуются люди. В поиск попадают только страницы, где товаров не меньше
 * brand_pages.min_products: меньше — тонкая страница без пользы для поиска,
 * она закрыта noindex и не входит в sitemap. Страницы есть только у разделов
 * из конфига.
 */
class BrandPages
{
    /**
     * Страница существует: раздел из конфига и в нём есть товар производителя.
     */
    public function exists(Category $category, Manufacturer $manufacturer): bool
    {
        return $this->isEnabledFor($category) && $this->productCount($category, $manufacturer) >= 1;
    }

    /**
     * Страница нужна поиску: товаров достаточно, чтобы она была не тонкой.
     */
    public function isIndexable(Category $category, Manufacturer $manufacturer): bool
    {
        return $this->isEnabledFor($category) && $this->productCount($category, $manufacturer) >= $this->minProducts();
    }

    /**
     * Адрес производителя в разделе: своя страница, а если её нет (раздел не
     * из списка или нет товаров) — раздел с отбором по производителю.
     */
    public function url(Category $category, Manufacturer $manufacturer): string
    {
        return $this->exists($category, $manufacturer)
            ? route('category.manufacturer', [$category, $manufacturer])
            : route('category.show', ['category' => $category, 'manufacturer' => [$manufacturer->id]]);
    }

    /**
     * Производители раздела со страницей, по алфавиту. По умолчанию все;
     * $indexableOnly — только те, что нужны поиску (плитки, sitemap).
     *
     * @return Collection<int, Manufacturer>
     */
    public function manufacturers(Category $category, bool $indexableOnly = false): Collection
    {
        $ids = $this->changedAtByManufacturer($category, $indexableOnly ? $this->minProducts() : 1)->keys();

        return Manufacturer::query()->whereIn('id', $ids)->orderBy('name')->get();
    }

    /**
     * Индексируемые страницы производителей для sitemap: раздел, производитель
     * и дата последнего изменения их товаров.
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
            $changedAt = $this->changedAtByManufacturer($category, $this->minProducts());
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
     * для тех, у кого товаров не меньше $minProducts.
     *
     * @return Collection<int, ?Carbon>
     */
    private function changedAtByManufacturer(Category $category, int $minProducts): Collection
    {
        if (! $this->isEnabledFor($category)) {
            return collect();
        }

        return Product::query()
            ->where('status', true)
            ->whereNotNull('manufacturer_id')
            ->inCategoryTree($category)
            ->groupBy('manufacturer_id')
            ->havingRaw('count(*) >= ?', [$minProducts])
            ->select('manufacturer_id', DB::raw('max(updated_at) as changed_at'))
            ->get()
            ->mapWithKeys(fn (Product $row): array => [$row->manufacturer_id => $row->changed_at === null ? null : Carbon::parse($row->changed_at)]);
    }
}
