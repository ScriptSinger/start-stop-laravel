<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Page;
use App\Models\Product;
use App\Services\Catalog\BrandPages;
use App\Services\Seo\TrackingParams;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Адреса старого сайта на OpenCart: товар — /zver-60-ah-o-p, раздел —
 * /avtotovari/kabeli (путь из родителей), марка в разделе —
 * /akkumulyatori/titan, инфостраница — /uslugi. Поисковики держат их в
 * индексе, поэтому ведём 301 на новые адреса, а не отдаём 404.
 *
 * При импорте slug взят из SEO-адреса старого сайта, так что нужный
 * объект находится по последнему сегменту пути.
 */
class LegacyUrlController extends Controller
{
    public function __invoke(Request $request, BrandPages $brandPages): RedirectResponse
    {
        $segments = array_values(array_filter(explode('/', trim($request->path(), '/'))));

        $target = $this->target($segments, $request, $brandPages);

        abort_if($target === null, 404);

        return redirect()->to($this->withIncomingQuery($target, $request), 301);
    }

    /**
     * Метки рекламы со старой ссылки (utm_*, yclid) переносим на новый адрес:
     * иначе Метрика потеряет источник визита. Прочие параметры отбрасываем,
     * параметры самого нового адреса главнее.
     */
    private function withIncomingQuery(string $target, Request $request): string
    {
        $incoming = TrackingParams::from($request);

        if ($incoming === []) {
            return $target;
        }

        $parts = explode('?', $target, 2);
        parse_str($parts[1] ?? '', $own);

        return $parts[0].'?'.http_build_query([...$incoming, ...$own]);
    }

    /**
     * Корень пути — товар, вложенный путь — раздел: у старого сайта товары лежали
     * в корне, а разделы цепочкой родителей. Если последний сегмент вложенного
     * пути — не раздел, это может быть марка внутри раздела.
     *
     * @param  list<string>  $segments
     */
    private function target(array $segments, Request $request, BrandPages $brandPages): ?string
    {
        $slug = (string) last($segments);
        $nested = count($segments) > 1;

        $product = fn (): ?string => ($model = Product::query()->where('status', true)->where('slug', $slug)->first()) ? route('product.show', $model) : null;
        $category = fn (): ?string => ($model = Category::query()->where('status', true)->where('slug', $slug)->first()) ? $this->categoryUrl($model, $request) : null;
        $page = fn (): ?string => ($model = Page::query()->where('status', true)->where('slug', $slug)->first()) ? route('page.show', $model) : null;
        $brandInCategory = fn (): ?string => $nested ? $this->brandInCategoryUrl($segments, $request, $brandPages) : null;

        $order = $nested ? [$category, $product, $page, $brandInCategory] : [$product, $category, $page];

        foreach ($order as $resolve) {
            if (($target = $resolve()) !== null) {
                return $target;
            }
        }

        return null;
    }

    /**
     * Номер страницы со старого адреса (?page=3) сохраняем: страницы
     * списка — самостоятельные.
     *
     * @param  array<string, mixed>  $filter
     */
    private function categoryUrl(Category $category, Request $request, array $filter = []): string
    {
        $page = $request->integer('page');

        return route('category.show', [
            'category' => $category,
            ...$filter,
            ...($page > 1 ? ['page' => $page] : []),
        ]);
    }

    /**
     * /akkumulyatori/titan → страница производителя в разделе, а если у неё
     * нет страницы (мало товаров, раздел не в списке) — раздел с фильтром. У старого
     * сайта страницы марки внутри раздела получали числовой суффикс
     * (titan-1, bushido-2), у производителя его нет — отрезаем.
     *
     * @param  list<string>  $segments
     */
    private function brandInCategoryUrl(array $segments, Request $request, BrandPages $brandPages): ?string
    {
        $brandSlug = array_pop($segments);

        $category = Category::query()->where('status', true)->where('slug', last($segments))->first();

        if ($category === null) {
            return null;
        }

        $manufacturer = Manufacturer::query()
            ->whereIn('slug', array_unique([$brandSlug, preg_replace('/-\d+$/', '', $brandSlug)]))
            ->orderByRaw('slug = ? DESC', [$brandSlug])
            ->first();

        if ($manufacturer === null) {
            return null;
        }

        if ($brandPages->exists($category, $manufacturer)) {
            return route('category.manufacturer', [$category, $manufacturer, ...($request->integer('page') > 1 ? ['page' => $request->integer('page')] : [])]);
        }

        return $this->categoryUrl($category, $request, ['manufacturer' => [$manufacturer->id]]);
    }
}
