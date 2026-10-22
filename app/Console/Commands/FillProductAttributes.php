<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Catalog\ProductAttributeFiller;
use App\Services\Import\OneCPriceReader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

#[Signature('products:fill-attributes {--price-list= : Прайс 1С: название из него помогает найти ток и габариты} {--force : Записать; без флага — только отчёт} {--report= : Путь к CSV: что получит каждый товар}')]
#[Description('Добавить товарам недостающие характеристики (ёмкость, полярность, ток, габариты, вязкость и др.), выведя их из названий')]
class FillProductAttributes extends Command
{
    /**
     * Без --force ничего не пишет. Только добавляет: существующие значения не
     * меняются, характеристика с уже заданным значением пропускается. Берутся
     * характеристики разделов товара. Перед запуском: php artisan db:backup.
     */
    public function handle(ProductAttributeFiller $filler, OneCPriceReader $reader): int
    {
        $onecNames = [];

        if ($this->option('price-list')) {
            try {
                $onecNames = collect($reader->read($this->option('price-list'))['rows'])->pluck('name', 'external_id')->all();
            } catch (InvalidArgumentException|RuntimeException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }
        }

        $plan = $filler->plan(Product::query()->get(), $onecNames);

        $this->report($plan);

        if ($this->option('report')) {
            $this->writeReport($this->option('report'), $plan);
        }

        if (! $this->option('force')) {
            $this->warn('Пробный прогон: характеристики не добавлены. Для записи добавьте --force.');

            return self::SUCCESS;
        }

        $this->info('Характеристики добавлены товарам: '.$filler->apply($plan).'.');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, array{product: Product, existing: array<string, list<int>>, create: array<string, list<string>>, label: array<string, list<string>>}>  $plan
     */
    private function report(Collection $plan): void
    {
        $this->components->twoColumnDetail('Товаров получат характеристики', (string) $plan->count());
        $this->components->twoColumnDetail('…из них активных', (string) $plan->filter(fn (array $item): bool => $item['product']->status)->count());

        $counts = [];

        foreach ($plan as $item) {
            foreach ($item['label'] as $name => $labels) {
                $counts[$name] = ($counts[$name] ?? 0) + 1;
            }
        }

        arsort($counts);
        $this->table(['Характеристика', 'Товаров получат'], collect($counts)->map(fn (int $count, string $name): array => [$name, $count])->values()->all());

        $new = $plan->flatMap(fn (array $item): array => collect($item['create'])->flatMap(fn (array $values, string $name): array => array_map(fn (string $value): string => "{$name}: {$value}", $values))->all())->unique();
        $this->components->twoColumnDetail('Новых значений справочников', (string) $new->count());
    }

    /**
     * @param  Collection<int, array{product: Product, existing: array<string, list<int>>, create: array<string, list<string>>, label: array<string, list<string>>}>  $plan
     */
    private function writeReport(string $path, Collection $plan): void
    {
        $handle = fopen($path, 'w');

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['id', 'Товар', 'Код 1С', 'Статус', 'Что будет добавлено'], ';', '"', '');

        foreach ($plan as $item) {
            fputcsv($handle, [
                $item['product']->id,
                $item['product']->name,
                $item['product']->external_id,
                $item['product']->status ? 'активен' : 'скрыт',
                collect($item['label'])->map(fn (array $labels, string $name): string => "{$name}: ".implode(', ', $labels))->implode('; '),
            ], ';', '"', '');
        }

        fclose($handle);

        $this->line("План по каждому товару: {$path}");
    }
}
