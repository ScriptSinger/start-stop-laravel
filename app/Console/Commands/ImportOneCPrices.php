<?php

namespace App\Console\Commands;

use App\Services\Import\OneCImportReport;
use App\Services\Import\OneCPriceImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

#[Signature('import:onec {file : Прайс из 1С или «Торгового дома» (.csv)} {--force : Записать в базу; без флага — только отчёт} {--not-found= : Путь к CSV для строк файла, которых нет на сайте}')]
#[Description('Обновить цену и остаток товаров по прайсу из 1С / «Торгового дома» (поиск товара по external_id)')]
class ImportOneCPrices extends Command
{
    /**
     * Без --force ничего не пишет — показывает, что изменится. Правила:
     *  - товар ищется по external_id (код 1С), название и прочие поля не трогаются;
     *  - пустая или нулевая цена — цена на сайте не меняется;
     *  - пустой остаток считается нулём;
     *  - товары сайта, которых нет в файле, не меняются;
     *  - строки файла без товара на сайте не создают товар, а попадают в отчёт.
     * Перед запуском: php artisan db:backup.
     */
    public function handle(OneCPriceImporter $importer): int
    {
        $path = $this->argument('file');

        if (! is_file($path)) {
            $this->error("Файл {$path} не найден.");

            return self::FAILURE;
        }

        try {
            $report = $importer->preview($path);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->report($report);

        if ($this->option('not-found') && $report->notFound->isNotEmpty()) {
            $this->writeNotFound($this->option('not-found'), $report->notFound);
        }

        if (! $this->option('force')) {
            $this->warn('Пробный прогон: база не изменена. Для записи добавьте --force.');

            return self::SUCCESS;
        }

        $this->info('Обновлено товаров: '.$importer->apply($report).'.');

        return self::SUCCESS;
    }

    private function report(OneCImportReport $report): void
    {
        $this->components->twoColumnDetail('Формат файла', $report->format);
        $this->components->twoColumnDetail('Строк в файле', (string) $report->rows);
        $this->components->twoColumnDetail('Повторов кода (берётся последняя строка)', (string) $report->duplicates);
        $this->components->twoColumnDetail('Найдено на сайте', (string) $report->found);
        $this->components->twoColumnDetail('Нет на сайте (не создаются)', (string) $report->notFound->count());
        $this->components->twoColumnDetail('Без цены в файле (цена не меняется)', (string) $report->withoutPrice);
        $this->components->twoColumnDetail('Изменится цена', (string) $report->priceChanges());
        $this->components->twoColumnDetail('Изменится остаток', (string) $report->quantityChanges());

        if ($report->changes->isNotEmpty()) {
            $this->line('Примеры изменений:');
            $this->table(['Код', 'Название на сайте', 'Цена', 'Остаток'], $report->changes->take(10)->map(fn (array $change): array => [
                $change['product']->external_id,
                mb_strimwidth($change['product']->name, 0, 45, '…'),
                $change['price'] ? "{$change['price'][0]} → {$change['price'][1]}" : '',
                $change['quantity'] ? "{$change['quantity'][0]} → {$change['quantity'][1]}" : '',
            ])->all());
        }

        if ($report->notFound->isNotEmpty()) {
            $this->line('Нет на сайте (первые 10; полный список — опция --not-found=путь.csv):');
            $this->table(['Код', 'Название в файле'], $report->notFound->take(10)->map(fn (array $row): array => [$row['external_id'], mb_strimwidth($row['name'], 0, 60, '…')])->all());
        }
    }

    /**
     * @param  Collection<string, array{external_id: string, name: string, price: ?float, quantity: ?float}>  $notFound
     */
    private function writeNotFound(string $path, Collection $notFound): void
    {
        $handle = fopen($path, 'w');

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['Код', 'Название', 'Цена', 'Остаток'], ';', '"', '');

        foreach ($notFound as $row) {
            fputcsv($handle, [$row['external_id'], $row['name'], $row['price'], $row['quantity']], ';', '"', '');
        }

        fclose($handle);

        $this->line("Список строк, которых нет на сайте: {$path}");
    }
}
