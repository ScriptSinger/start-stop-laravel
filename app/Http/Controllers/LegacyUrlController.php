<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Адреса старого сайта на OpenCart: товар — /zver-60-ah-o-p, раздел —
 * /avtotovari/kabeli (путь из родителей), инфостраница — /uslugi. Поисковики
 * держат их в индексе, поэтому ведём 301 на новые адреса, а не отдаём 404.
 *
 * При импорте slug взят из SEO-адреса старого сайта, так что нужный
 * объект находится по последнему сегменту пути.
 */
class LegacyUrlController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $slug = last(explode('/', trim($request->path(), '/')));

        $target = $this->target($slug, substr_count(trim($request->path(), '/'), '/') > 0);

        abort_if($target === null, 404);

        return redirect()->to($target, 301);
    }

    /**
     * Корень пути — товар, вложенный путь — раздел: у старого сайта товары лежали
     * в корне, а разделы цепочкой родителей.
     */
    private function target(string $slug, bool $nested): ?string
    {
        $product = fn (): ?string => ($model = Product::query()->where('status', true)->where('slug', $slug)->first()) ? route('product.show', $model) : null;
        $category = fn (): ?string => ($model = Category::query()->where('status', true)->where('slug', $slug)->first()) ? route('category.show', $model) : null;
        $page = fn (): ?string => ($model = Page::query()->where('status', true)->where('slug', $slug)->first()) ? route('page.show', $model) : null;

        $order = $nested ? [$category, $product, $page] : [$product, $category, $page];

        foreach ($order as $resolve) {
            if (($target = $resolve()) !== null) {
                return $target;
            }
        }

        return null;
    }
}
