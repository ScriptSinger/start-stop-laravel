<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Import\OneCCategoryAssigner;
use App\Services\Import\OneCPriceReader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

#[Signature('products:assign-onec-categories {price-list : Прайс 1С (price_list.csv)} {--force : Записать; без флага — только отчёт}')]
#[Description('Проставить разделы товарам без раздела по названию из 1С; недостающие разделы создаются скрытыми')]
class AssignOneCCategories extends Command
{
    /**
     * Без --force ничего не пишет. Берёт товары с кодом 1С, у которых нет ни одного
     * раздела; раздел определяется по названию строки в прайсе 1С. Новые разделы
     * создаются скрытыми (status = 0) вместе с родителем. Перед запуском: php artisan db:backup.
     */
    public function handle(OneCPriceReader $reader, OneCCategoryAssigner $assigner): int
    {
        try {
            $names = collect($reader->read($this->argument('price-list'))['rows'])->pluck('name', 'external_id');
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $items = Product::query()
            ->whereIn('external_id', $names->keys()->all())
            ->doesntHave('categories')
            ->get()
            ->map(fn (Product $product): array => ['product' => $product, 'name' => (string) $names[$product->external_id]]);

        $plan = $assigner->plan($items);

        $this->report($plan);

        if (! $this->option('force')) {
            $this->warn('Пробный прогон: разделы не назначены. Для записи добавьте --force.');

            return self::SUCCESS;
        }

        $result = $assigner->apply($plan);

        $this->info("Товаров привязано к разделам: {$result['attached']}.");

        if ($result['created'] !== []) {
            $this->info('Созданы скрытые разделы: '.implode(', ', $result['created']).'.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, array{product: Product, category: ?string, exists: bool}>  $plan
     */
    private function report(Collection $plan): void
    {
        $this->components->twoColumnDetail('Товаров без раздела', (string) $plan->count());
        $this->components->twoColumnDetail('Раздел определён', (string) $plan->whereNotNull('category')->count());
        $this->components->twoColumnDetail('Раздел не определён (остаются без раздела)', (string) $plan->whereNull('category')->count());

        $this->table(['Раздел', 'Товаров', 'На сайте'], $plan->whereNotNull('category')->groupBy('category')->map(fn (Collection $group, string $category): array => [
            $category,
            $group->count(),
            $group->first()['exists'] ? 'есть' : 'будет создан (скрытый)',
        ])->sortByDesc(1)->values()->all());
    }
}
