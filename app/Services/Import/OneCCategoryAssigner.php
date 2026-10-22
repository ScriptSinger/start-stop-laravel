<?php

namespace App\Services\Import;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Разделы для товаров из 1С по названию (OneCProductSpecs). Существующие разделы
 * ищутся по названию; недостающие из OneCProductSpecs::newCategories() создаются
 * скрытыми, вместе с родителем.
 */
class OneCCategoryAssigner
{
    public function __construct(private readonly OneCProductSpecs $specs) {}

    /**
     * @param  Collection<int, array{product: Product, name: string}>  $items  товар и название из 1С
     * @return Collection<int, array{product: Product, category: ?string, exists: bool}>
     */
    public function plan(Collection $items): Collection
    {
        $existing = Category::query()->pluck('name')->map(fn (string $name): string => trim($name))->flip();

        return $items->map(function (array $item) use ($existing): array {
            $category = $this->specs->category($item['name']);

            return [
                'product' => $item['product'],
                'category' => $category,
                'exists' => $category !== null && $existing->has($category),
            ];
        })->values();
    }

    /**
     * Прикрепить товары к разделам. Возвращает, сколько привязано и какие разделы созданы.
     *
     * @param  Collection<int, array{product: Product, category: ?string, exists: bool}>  $plan
     * @return array{attached: int, created: list<string>}
     */
    public function apply(Collection $plan): array
    {
        $before = Category::query()->count();
        $created = [];
        $attached = 0;

        foreach ($plan as $item) {
            if ($item['category'] === null) {
                continue;
            }

            $category = $this->findOrCreate($item['category'], $created);

            if ($category !== null) {
                $item['product']->categories()->syncWithoutDetaching([$category->id]);
                $attached++;
            }
        }

        return ['attached' => $attached, 'created' => $before === Category::query()->count() ? [] : $created];
    }

    /**
     * @param  list<string>  $created
     */
    public function findOrCreate(string $name, array &$created = []): ?Category
    {
        $category = Category::query()->get()->first(fn (Category $category): bool => trim($category->name) === $name);

        if ($category !== null) {
            return $category;
        }

        $newCategories = OneCProductSpecs::newCategories();

        if (! array_key_exists($name, $newCategories)) {
            return null;
        }

        $parent = $newCategories[$name] !== null ? $this->findOrCreate($newCategories[$name], $created) : null;

        $created[] = $name;

        return Category::query()->create([
            'parent_id' => $parent?->id,
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'sort_order' => 0,
            'status' => false,
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug(strtr($name, ['щ' => 'shch', 'Щ' => 'Shch']), '-', 'ru') ?: (string) Str::uuid();
        $slug = $base;
        $suffix = 2;

        while (Category::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
