<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Import\OneCNewProducts;
use App\Services\Import\OneCPriceReader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

#[Signature('products:create-from-onec {price-list : Прайс 1С (price_list.csv)} {codes : CSV со списком кодов 1С, для которых создать товары (первая колонка, первая строка — заголовок)} {--force : Создать; без флага — только отчёт} {--report= : Путь к CSV с планом по каждому товару}')]
#[Description('Создать на сайте скрытые товары из строк прайса 1С по списку кодов: названия, производители и характеристики — в стиле сайта')]
class CreateProductsFromOneC extends Command
{
    /**
     * Без --force ничего не пишет — показывает, что будет создано. Товары создаются
     * скрытыми (status = 0): менеджер просматривает их и включает. Коды, у которых
     * на сайте уже есть товар (даже удалённый), пропускаются. Перед запуском:
     * php artisan db:backup.
     */
    public function handle(OneCPriceReader $reader, OneCNewProducts $newProducts): int
    {
        try {
            $rows = collect($reader->read($this->argument('price-list'))['rows'])->keyBy('external_id');
            $codes = $this->readCodes($this->argument('codes'));
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $existing = Product::withTrashed()->whereIn('external_id', $codes->all())->pluck('external_id')->flip();
        $missingInFile = $codes->reject(fn (string $code): bool => $rows->has($code));
        $skipped = $codes->filter(fn (string $code): bool => $rows->has($code) && $existing->has($code));
        $plan = $newProducts->plan($codes->filter(fn (string $code): bool => $rows->has($code) && ! $existing->has($code))->map(fn (string $code): array => $rows[$code]));

        $this->report($codes, $missingInFile, $skipped, $plan);

        if ($this->option('report')) {
            $this->writeReport($this->option('report'), $plan);
        }

        if (! $this->option('force')) {
            $this->warn('Пробный прогон: товары не созданы. Для создания добавьте --force.');

            return self::SUCCESS;
        }

        $this->info('Создано скрытых товаров: '.$newProducts->create($plan).'.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, string>
     */
    private function readCodes(string $path): Collection
    {
        if (! is_file($path)) {
            throw new RuntimeException("Файл {$path} не найден.");
        }

        $contents = (string) preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($path));
        $lines = array_values(array_filter(preg_split('/\R/u', $contents), fn (string $line): bool => trim($line) !== ''));

        return collect(array_slice($lines, 1))->map(fn (string $line): string => trim(str_getcsv($line, ';', '"', '')[0] ?? ''))->filter()->unique()->values();
    }

    /**
     * @param  Collection<int, string>  $codes
     * @param  Collection<int, string>  $missingInFile
     * @param  Collection<int, string>  $skipped
     * @param  Collection<int, array<string, mixed>>  $plan
     */
    private function report(Collection $codes, Collection $missingInFile, Collection $skipped, Collection $plan): void
    {
        $this->components->twoColumnDetail('Кодов в списке', (string) $codes->count());
        $this->components->twoColumnDetail('Нет в прайсе (пропущены)', (string) $missingInFile->count());
        $this->components->twoColumnDetail('Уже есть на сайте (пропущены)', (string) $skipped->count());
        $this->components->twoColumnDetail('Будет создано (скрытыми)', (string) $plan->count());
        $this->components->twoColumnDetail('…аккумуляторов', (string) $plan->where('is_battery', true)->count());
        $this->components->twoColumnDetail('…с разделом', (string) $plan->whereNotNull('category_id')->count());
        $this->components->twoColumnDetail('…без раздела', (string) $plan->whereNull('category_id')->count());
        $this->components->twoColumnDetail('Новых производителей', (string) $plan->filter(fn (array $item): bool => $item['manufacturer'] !== null && $item['manufacturer']['id'] === null)->pluck('manufacturer.name')->unique()->count());

        $this->line('Разделы:');
        $this->table(['Раздел', 'Товаров'], $plan->groupBy(fn (array $item): string => $item['category'] ?? '— без раздела —')->map(fn (Collection $group, string $category): array => [$category, $group->count()])->sortByDesc(1)->values()->all());

        $this->line('Примеры (первые 12):');
        $this->table(['Код 1С', 'Название на сайте', 'Раздел', 'Производитель'], $plan->take(12)->map(fn (array $item): array => [
            $item['external_id'],
            mb_strimwidth($item['name'], 0, 48, '…'),
            $item['category'] ?? '—',
            $item['manufacturer']['name'] ?? '—',
        ])->all());
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $plan
     */
    private function writeReport(string $path, Collection $plan): void
    {
        $handle = fopen($path, 'w');

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['Код 1С', 'Название в 1С', 'Название на сайте', 'Адрес (slug)', 'Код товара', 'Артикул', 'Производитель', 'Раздел', 'Характеристики', 'Цена', 'Остаток', 'Предупреждения'], ';', '"', '');

        foreach ($plan as $item) {
            fputcsv($handle, [
                $item['external_id'],
                $item['source_name'],
                $item['name'],
                $item['slug'],
                $item['code'],
                $item['sku'],
                $item['manufacturer']['name'] ?? '',
                $item['category'] ?? '',
                collect($item['attributes'])->map(fn (string $value, string $key): string => "{$key}: {$value}")->implode('; '),
                $item['price'],
                $item['quantity'],
                implode('; ', $item['warnings']),
            ], ';', '"', '');
        }

        fclose($handle);

        $this->line("План по каждому товару: {$path}");
    }
}
