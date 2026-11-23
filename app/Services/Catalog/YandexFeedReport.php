<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Диагностика YML-фида: сколько включённых товаров в него не попало и почему.
 * Ничего не пишет — только считает по тем же правилам, что YandexFeed.
 * Товар относится к первой подходящей причине по порядку из REASONS.
 */
class YandexFeedReport
{
    public const REASONS = [
        'price' => 'а) нет цены или цена 0',
        'photo' => 'б) нет фото',
        'manufacturer' => 'в) нет производителя',
        'category' => 'г) нет включённого раздела',
        'not_allowed' => 'д) раздел не входит в shop.feed.*',
        'stock' => 'е) нет в наличии и не под заказ',
        'other' => 'ж) прочее (не подошёл readyForExport())',
    ];

    public const EXAMPLES_LIMIT = 10;

    public function __construct(private readonly YandexFeed $feed) {}

    /**
     * @return array{
     *     total: int,
     *     exported: int,
     *     reasons: array<string, array{label: string, count: int, examples: list<array{id: int, name: string, categories: string}>}>,
     *     sections: list<array{id: int, name: string, parent: string, allowed: bool, exported: int, skipped: int}>,
     *     skippedRoots: list<array{id: int, name: string, products: int}>
     * }
     */
    public function build(): array
    {
        $categories = $this->feed->categories();
        $allowedIds = $this->feed->allowedCategoryIds($categories);
        $readyIds = Product::query()->readyForExport()->pluck('id')->flip();

        $reasons = array_map(fn (string $label): array => ['label' => $label, 'count' => 0, 'examples' => []], self::REASONS);
        $sections = [];
        $skippedRoots = [];
        $total = 0;
        $exported = 0;

        Product::query()
            ->where('status', true)
            ->with(['manufacturer:id,name', 'categories:id,name'])
            ->chunkById(200, function (Collection $products) use ($categories, $allowedIds, $readyIds, &$reasons, &$sections, &$skippedRoots, &$total, &$exported): void {
                foreach ($products as $product) {
                    $total++;
                    $reason = $this->reason($product, $categories, $allowedIds, $readyIds->has($product->id));

                    if ($reason === null) {
                        $exported++;
                    } else {
                        $reasons[$reason]['count']++;

                        if (count($reasons[$reason]['examples']) < self::EXAMPLES_LIMIT) {
                            $reasons[$reason]['examples'][] = [
                                'id' => $product->id,
                                'name' => $product->name,
                                'categories' => $product->categories->pluck('name')->implode(', ') ?: '—',
                            ];
                        }
                    }

                    foreach ($product->categories as $category) {
                        $sections[$category->id] ??= ['exported' => 0, 'skipped' => 0];
                        $sections[$category->id][$reason === null ? 'exported' : 'skipped']++;
                    }

                    if ($reason === 'not_allowed') {
                        $root = $this->root($categories->get($product->categories->first(fn (Category $category): bool => $categories->has($category->id))?->id), $categories);

                        if ($root !== null) {
                            $skippedRoots[$root->id] = ($skippedRoots[$root->id] ?? 0) + 1;
                        }
                    }
                }
            });

        return [
            'total' => $total,
            'exported' => $exported,
            'reasons' => $reasons,
            'sections' => $this->sectionRows($sections, $categories, $allowedIds),
            'skippedRoots' => $this->rootRows($skippedRoots, $categories),
        ];
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @param  array<int, true>  $allowedIds
     */
    private function reason(Product $product, Collection $categories, array $allowedIds, bool $isReady): ?string
    {
        $categoryIds = $product->categories->pluck('id');

        return match (true) {
            $product->displayPrice() <= 0 => 'price',
            blank($product->image) || $product->image === 'no_image.png' => 'photo',
            $product->manufacturer_id === null || $product->manufacturer === null => 'manufacturer',
            ! $categoryIds->contains(fn (int $id): bool => $categories->has($id)) => 'category',
            ! $categoryIds->contains(fn (int $id): bool => isset($allowedIds[$id])) => 'not_allowed',
            $product->quantity <= 0 && ! $product->isAvailableOnOrder() => 'stock',
            ! $isReady => 'other',
            default => null,
        };
    }

    /**
     * @param  Collection<int, Category>  $categories
     */
    private function root(?Category $category, Collection $categories): ?Category
    {
        for ($depth = 0; $category !== null && $category->parent_id !== null && $categories->has($category->parent_id) && $depth < 10; $depth++) {
            $category = $categories->get($category->parent_id);
        }

        return $category === null ? null : $categories->get($category->id);
    }

    /**
     * @param  array<int, array{exported: int, skipped: int}>  $sections
     * @param  Collection<int, Category>  $categories
     * @param  array<int, true>  $allowedIds
     * @return list<array{id: int, name: string, parent: string, allowed: bool, exported: int, skipped: int}>
     */
    private function sectionRows(array $sections, Collection $categories, array $allowedIds): array
    {
        $rows = [];

        foreach ($sections as $id => $counts) {
            $category = $categories->get($id);

            $rows[] = [
                'id' => $id,
                'name' => $category?->name ?? "#{$id} (выключен)",
                'parent' => (string) ($category?->parent_id !== null ? ($categories->get($category->parent_id)?->name ?? $category->parent_id) : ''),
                'allowed' => isset($allowedIds[$id]),
                'exported' => $counts['exported'],
                'skipped' => $counts['skipped'],
            ];
        }

        usort($rows, fn (array $a, array $b): int => [$b['skipped'], $a['id']] <=> [$a['skipped'], $b['id']]);

        return $rows;
    }

    /**
     * @param  array<int, int>  $counts
     * @param  Collection<int, Category>  $categories
     * @return list<array{id: int, name: string, products: int}>
     */
    private function rootRows(array $counts, Collection $categories): array
    {
        arsort($counts);

        return array_map(
            fn (int $id, int $products): array => ['id' => $id, 'name' => $categories->get($id)->name, 'products' => $products],
            array_keys($counts),
            array_values($counts),
        );
    }
}
