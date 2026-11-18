<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Адреса старого сайта на OpenCart вида /index.php?route=product/product&product_id=3040.
 * Для Laravel это просто главная, и она отвечала бы 200 на тысячи разных адресов —
 * дубли главной в индексе. Ведём 301 на новую страницу: id товаров, разделов
 * и инфостраниц при импорте сохранены.
 */
class RedirectLegacyIndex
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe() || parse_url($request->getRequestUri(), PHP_URL_PATH) !== '/index.php') {
            return $next($request);
        }

        $path = $this->path($request);

        abort_if($path === null, 404);

        // Адрес собираем сами: при запросе /index.php?… Laravel считает /index.php
        // базой приложения и дописывает его в каждую ссылку (…/index.php/catalog/…),
        // а редирект на главную зациклился бы.
        return new RedirectResponse($request->getSchemeAndHttpHost().$path, 301);
    }

    /**
     * Путь новой страницы от корня сайта.
     */
    private function path(Request $request): ?string
    {
        return match ($request->query('route', '')) {
            '', 'common/home' => route('home', [], false),
            'information/contact' => route('contact', [], false),
            'product/product' => $this->productUrl($request->integer('product_id')),
            'product/category' => $this->categoryUrl($request),
            'information/information', 'information/information/agree' => $this->pageUrl($request->integer('information_id')),
            default => null,
        };
    }

    private function productUrl(int $id): ?string
    {
        $product = Product::query()->where('status', true)->find($id);

        return $product === null ? null : route('product.show', $product, false);
    }

    /**
     * path=1_20 — цепочка id родителей, нужен последний.
     */
    private function categoryUrl(Request $request): ?string
    {
        $path = explode('_', (string) $request->query('path', ''));

        $category = Category::query()->where('status', true)->find((int) end($path));

        return $category === null ? null : route('category.show', $category, false);
    }

    private function pageUrl(int $id): ?string
    {
        $page = Page::query()->where('status', true)->find($id);

        return $page === null ? null : route('page.show', $page, false);
    }
}
