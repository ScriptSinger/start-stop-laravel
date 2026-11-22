<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogFilterRequest;
use App\Models\BrandPage;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\Catalog\BrandPages;
use App\Services\Catalog\CatalogFacets;
use App\Services\Catalog\CatalogFilter;
use App\Services\Catalog\CategoryMenu;
use App\Services\Catalog\GuideLocator;
use App\Services\Seo\BreadcrumbsSeo;
use App\Services\Seo\TrackingParams;
use Artesaos\SEOTools\Facades\SEOMeta;
use Artesaos\SEOTools\Facades\SEOTools;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function show(CatalogFilterRequest $request, Category $category, BrandPages $brandPages, CatalogFacets $facets, CategoryMenu $categoryMenu, BreadcrumbsSeo $breadcrumbs, GuideLocator $guides): View|RedirectResponse
    {
        if (($brandPage = $this->brandPageRedirect($request, $category, $brandPages)) !== null) {
            return $brandPage;
        }

        return $this->page($request, $category, null, $brandPages, $facets, $categoryMenu, $breadcrumbs, $guides);
    }

    /**
     * Фильтр из одного производителя без других условий — та же выборка, что
     * страница производителя: ведём на неё 301-м, чтобы в индексе был один адрес.
     * Остальные параметры (сортировка, номер страницы, метки рекламы utm_*, yclid) переносятся.
     */
    private function brandPageRedirect(CatalogFilterRequest $request, Category $category, BrandPages $brandPages): ?RedirectResponse
    {
        $filter = $request->toFilter();

        if (count($filter->manufacturerIds) !== 1 || $filter->withManufacturers([])->isActive()) {
            return null;
        }

        $manufacturer = Manufacturer::query()->find($filter->manufacturerIds[0]);

        if ($manufacturer === null || ! $brandPages->exists($category, $manufacturer)) {
            return null;
        }

        return redirect()->route('category.manufacturer', [$category, $manufacturer, ...$request->only(['sort', 'limit', 'page']), ...TrackingParams::from($request)], 301);
    }

    /**
     * Страница «раздел + производитель»: бренд задан адресом, остальные
     * условия фильтра работают поверх него. Нет страницы у раздела или
     * у бренда мало товаров — 404, как у любого несуществующего адреса.
     */
    public function manufacturer(CatalogFilterRequest $request, Category $category, Manufacturer $manufacturer, BrandPages $brandPages, CatalogFacets $facets, CategoryMenu $categoryMenu, BreadcrumbsSeo $breadcrumbs, GuideLocator $guides): View
    {
        abort_unless($brandPages->exists($category, $manufacturer), 404);

        return $this->page($request, $category, $manufacturer, $brandPages, $facets, $categoryMenu, $breadcrumbs, $guides);
    }

    private function page(CatalogFilterRequest $request, Category $category, ?Manufacturer $manufacturer, BrandPages $brandPages, CatalogFacets $facets, CategoryMenu $categoryMenu, BreadcrumbsSeo $breadcrumbs, GuideLocator $guides): View
    {
        $filter = $manufacturer === null ? $request->toFilter() : $request->toFilter()->withManufacturers([$manufacturer->id]);
        $priceBounds = $facets->priceBounds($category);

        $products = $this->filteredProducts($category, $filter)
            ->withCardData()
            ->sortedBy($request->sort(), $request->hasChosenSort())
            ->paginate($request->perPage())
            ->withQueryString();

        $brandPage = $manufacturer === null ? null : $brandPages->content($category, $manufacturer);

        $this->describe($category, $manufacturer, $brandPage, $filter, $products, $breadcrumbs, $brandPages);

        return view('category', [
            'category' => $category,
            'manufacturer' => $manufacturer,
            'brandPage' => $brandPage,
            'categoryLinks' => $categoryMenu->subcategoryLinks($category),
            'guide' => $guides->forCategory($category),
            'products' => $products,
            'filter' => $filter,
            'sort' => $request->sort(),
            'perPage' => $request->perPage(),
            'manufacturerFacet' => $facets->manufacturers($category, $filter),
            'caseTypeFacet' => $facets->caseTypes($category, $filter),
            'attributeFacets' => $facets->attributes($category, $filter),
            // Границы шкалы цены — целыми рублями, наружу.
            'priceMin' => (int) floor($priceBounds['min']),
            'priceMax' => (int) ceil($priceBounds['max']),
        ]);
    }

    /**
     * Число товаров раздела с выбранным фильтром: на телефоне фильтр
     * применяется кнопкой «Показать N товаров», а не каждой галочкой.
     */
    public function count(CatalogFilterRequest $request, Category $category): JsonResponse
    {
        return response()->json([
            'count' => $this->filteredProducts($category, $request->toFilter())->count(),
        ]);
    }

    /**
     * Сортировка, цена и наличие — варианты страницы категории (или страницы
     * производителя в ней): canonical ведёт на неё саму. Страницы пагинации —
     * самостоятельные, со ссылками prev/next без фильтров.
     *
     * Отбор по производителю, типу корпуса или характеристике — страница
     * для людей, а не для поиска: noindex, follow, а canonical на ближайшую
     * индексируемую страницу (производитель со своей страницей — она, иначе раздел).
     */
    private function describe(Category $category, ?Manufacturer $manufacturer, ?BrandPage $brandPage, CatalogFilter $filter, LengthAwarePaginator $products, BreadcrumbsSeo $breadcrumbs, BrandPages $brandPages): void
    {
        $page = $products->currentPage();
        $name = $manufacturer === null ? $category->name : "{$category->name} {$manufacturer->name}";
        $ownTitle = $manufacturer === null ? $category->meta_title : $brandPage?->meta_title;
        $title = $ownTitle ?: ($manufacturer === null ? $category->name : $name.' — купить в Уфе');

        SEOTools::setTitle($page > 1 ? "{$title} — страница {$page}" : $title, appendDefault: ! $ownTitle);
        SEOTools::setDescription(($manufacturer === null ? $category->meta_description : $brandPage?->meta_description) ?: "{$name} — купить в Уфе в магазине ".config('shop.name').'. Цены, характеристики, наличие. Доставка по городу, гарантия.');

        $categoryUrl = route('category.show', $category);
        $pageRoute = fn (int $page): string => $manufacturer === null
            ? route('category.show', $page > 1 ? [$category, 'page' => $page] : $category)
            : route('category.manufacturer', $page > 1 ? [$category, $manufacturer, 'page' => $page] : [$category, $manufacturer]);

        $breadcrumbs->apply(array_values(array_filter([
            ['name' => config('shop.name'), 'url' => route('home')],
            $category->parent ? ['name' => $category->parent->name, 'url' => route('category.show', $category->parent)] : null,
            ['name' => $category->name, 'url' => $categoryUrl],
            $manufacturer ? ['name' => $manufacturer->name, 'url' => route('category.manufacturer', [$category, $manufacturer])] : null,
        ])));

        if ($manufacturer !== null && ! $brandPages->isIndexable($category, $manufacturer)) {
            SEOMeta::setRobots('noindex, follow');
        }

        if ($manufacturer === null && ! $filter->isActive() && $products->total() === 0) {
            SEOMeta::setRobots('noindex, follow');
        }

        if ($this->isSelection($filter, $manufacturer)) {
            SEOMeta::setRobots('noindex, follow');
            SEOMeta::setCanonical($this->indexableUrl($category, $manufacturer, $filter, $brandPages));

            return;
        }

        SEOMeta::setCanonical($pageRoute($page));

        if ($page > 1) {
            SEOMeta::setPrev($pageRoute($page - 1));
        }

        if ($products->hasMorePages()) {
            SEOMeta::setNext($pageRoute($page + 1));
        }
    }

    /**
     * Отбор, который не должен попадать в индекс. Производитель на странице
     * производителя задан адресом и отбором не считается.
     */
    private function isSelection(CatalogFilter $filter, ?Manufacturer $manufacturer): bool
    {
        return $filter->attributeValueIds !== []
            || $filter->caseTypeIds !== []
            || ($manufacturer === null && $filter->manufacturerIds !== []);
    }

    private function indexableUrl(Category $category, ?Manufacturer $manufacturer, CatalogFilter $filter, BrandPages $brandPages): string
    {
        if ($manufacturer === null && count($filter->manufacturerIds) === 1) {
            $candidate = Manufacturer::query()->find($filter->manufacturerIds[0]);
            $manufacturer = $candidate !== null && $brandPages->isIndexable($category, $candidate) ? $candidate : null;
        }

        return $manufacturer === null || ! $brandPages->isIndexable($category, $manufacturer)
            ? route('category.show', $category)
            : route('category.manufacturer', [$category, $manufacturer]);
    }

    /**
     * Товары раздела вместе с подразделами.
     *
     * @return Builder<Product>
     */
    private function filteredProducts(Category $category, CatalogFilter $filter): Builder
    {
        return Product::query()
            ->where('status', true)
            ->inCategoryTree($category)
            ->catalogFilter($filter);
    }
}
