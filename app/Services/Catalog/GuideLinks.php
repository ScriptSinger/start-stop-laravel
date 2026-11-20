<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Services\CarLanding\CarBrand;
use App\Services\CarLanding\CarLandingCatalog;

/**
 * Ссылки в тексте гайда (админка → Гайды): адреса не хранятся в тексте, их
 * подставляет страница, поэтому ссылки не ломаются при смене адресов.
 *
 *   [[category:1|аккумуляторы]]     — раздел каталога по id
 *   [[cars|подбор по автомобилю]]    — страница подбора по маркам
 *   [[brand:lada|для Lada]]          — посадочная марки
 *   [[block:catalog]]                — плашки разделов и популярных марок
 *
 * Если раздела или марки нет (отключён), остаётся простой текст.
 */
class GuideLinks
{
    public function __construct(private CarLandingCatalog $carLandings) {}

    public function render(string $body): string
    {
        $body = (string) preg_replace_callback(
            '~\[\[(category:(\d+)|cars|brand:([a-z0-9-]+))\|([^\]]+)\]\]~u',
            fn (array $match): string => $this->link($match),
            $body,
        );

        return (string) preg_replace_callback(
            '~(?:<p>\s*)?\[\[block:catalog\]\](?:\s*</p>)?~u',
            fn (): string => view('guide.catalog', [
                'categories' => $this->categoryLinks(),
                'brands' => $this->brandLinks(),
            ])->render(),
            $body,
        );
    }

    /**
     * @param  array<int, string>  $match
     */
    private function link(array $match): string
    {
        $text = e($match[4]);

        $url = match (true) {
            str_starts_with($match[1], 'category:') => $this->categoryUrl((int) $match[2]),
            $match[1] === 'cars' => route('car-landing.index'),
            default => $this->brandUrl($match[3]),
        };

        return $url === null ? $text : '<a href="'.e($url).'">'.$text.'</a>';
    }

    private function categoryUrl(int $id): ?string
    {
        $category = Category::query()->where('status', true)->find($id);

        return $category === null ? null : route('category.show', $category);
    }

    private function brandUrl(string $slug): ?string
    {
        return $this->carLandings->brand($slug) === null ? null : route('car-landing.brand', $slug);
    }

    /**
     * Разделы подбора АКБ (shop.battery_fitment.category_ids): легковые и грузовые.
     *
     * @return list<array{name: string, url: string}>
     */
    private function categoryLinks(): array
    {
        return Category::query()
            ->where('status', true)
            ->whereIn('id', config('shop.battery_fitment.category_ids'))
            ->orderBy('id')
            ->get()
            ->map(fn (Category $category): array => ['name' => $category->name, 'url' => route('category.show', $category)])
            ->all();
    }

    /**
     * Ходовые марки магазина (shop.car_landings.popular_brands), у которых есть посадочная.
     *
     * @return list<array{name: string, url: string}>
     */
    private function brandLinks(): array
    {
        return collect(config('shop.car_landings.popular_brands'))
            ->map(fn (string $slug): ?CarBrand => $this->carLandings->brand($slug))
            ->filter()
            ->map(fn (CarBrand $brand): array => ['name' => $brand->name, 'url' => route('car-landing.brand', $brand->slug)])
            ->values()
            ->all();
    }
}
