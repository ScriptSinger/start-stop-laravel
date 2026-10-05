<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Manufacturer;
use Illuminate\Support\Collection;

/**
 * «Популярные категории» на главной (модуль uni_category_wall_v2): разделы с
 * картинкой и выбранными ссылками — подкатегориями или производителями.
 */
class CategoryWall
{
    /**
     * @return Collection<int, array{category: Category, links: list<array{title: string, url: string}>}>
     */
    public function items(): Collection
    {
        return Category::query()
            ->whereNotNull('home_wall_sort')
            ->where('status', true)
            ->with([
                'wallManufacturers',
                'children' => fn ($query) => $query->where('status', true)->where('show_on_parent_wall', true)->orderBy('sort_order')->orderBy('name'),
            ])
            ->orderBy('home_wall_sort')
            ->get()
            ->map(fn (Category $category): array => [
                'category' => $category,
                'links' => [
                    ...$category->children->map(fn (Category $child): array => [
                        'title' => $child->name,
                        'url' => route('category.show', $child),
                    ]),
                    ...$category->wallManufacturers->map(fn (Manufacturer $manufacturer): array => [
                        'title' => $manufacturer->name,
                        'url' => route('category.show', ['category' => $category, 'manufacturer' => [$manufacturer->id]]),
                    ]),
                ],
            ]);
    }
}
