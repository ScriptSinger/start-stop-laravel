<?php

namespace App\Console\Commands;

use App\Enums\OneCLinkStatus;
use App\Models\Product;
use App\Services\Import\OneCPriceReader;
use App\Services\Import\OneCProductLinker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

#[Signature('products:link-onec {onec : Прайс 1С (price_list.csv)} {trade-house : Прайс «Торгового дома» (prices.csv)} {--force : Записать в базу; без флага — только отчёт} {--clear-unlinked : Вместе с --force: очистить external_id у товаров, которых нет в 1С} {--report= : Путь к CSV с результатом по каждому товару}')]
#[Description('Разово проставить external_id = код 1С: sku (код «Торгового дома») → название в «Торговом доме» → строка в 1С')]
class LinkProductsToOneC extends Command
{
    /**
     * Без --force ничего не пишет — показывает, что изменится. Привязываются
     * только однозначные совпадения; остальное идёт в отчёт, external_id таких
     * товаров не меняется (с --clear-unlinked — очищается: код «Торгового дома»
     * не должен остаться в поле, где ждут код 1С). Если итоговые external_id
     * повторяются, команда ничего не записывает. Перед запуском: php artisan db:backup.
     */
    public function handle(OneCPriceReader $reader, OneCProductLinker $linker): int
    {
        try {
            $oneC = $this->readFile($reader, $this->argument('onec'), OneCPriceReader::FORMAT_ONEC);
            $tradeHouse = $this->readFile($reader, $this->argument('trade-house'), OneCPriceReader::FORMAT_TRADE_HOUSE);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $products = Product::query()->whereNotNull('sku')->whereRaw("TRIM(sku) <> ''")->orderBy('id')->get();
        $results = $linker->link($products, $oneC, $tradeHouse);

        $finalIds = $this->finalExternalIds($results);
        $duplicates = $finalIds->filter()->duplicates();

        $this->report($results, $finalIds);

        if ($this->option('report')) {
            $this->writeReport($this->option('report'), $results, $finalIds);
        }

        if ($duplicates->isNotEmpty()) {
            $this->error('Итоговые external_id повторяются: '.$duplicates->unique()->implode(', ').'. Ничего не записано.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->warn('Пробный прогон: база не изменена. Для записи добавьте --force.');

            return self::SUCCESS;
        }

        $changed = $results->filter(fn (array $result): bool => $finalIds[$result['product']->id] !== $result['product']->external_id);

        DB::transaction(function () use ($changed, $finalIds): void {
            Product::query()->whereIn('id', $changed->pluck('product.id'))->update(['external_id' => null]);

            foreach ($changed as $result) {
                if ($finalIds[$result['product']->id] !== null) {
                    Product::query()->whereKey($result['product']->id)->update(['external_id' => $finalIds[$result['product']->id]]);
                }
            }
        });

        $this->info('Обновлено товаров: '.$changed->count().'.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{external_id: string, name: string, price: ?float, quantity: ?float}>
     */
    private function readFile(OneCPriceReader $reader, string $path, string $expectedFormat): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Файл {$path} не найден.");
        }

        $file = $reader->read($path);

        if ($file['format'] !== $expectedFormat) {
            throw new InvalidArgumentException("Файл {$path}: ожидался формат {$expectedFormat}, а определился {$file['format']}.");
        }

        return $file['rows'];
    }

    /**
     * Итоговый external_id каждого товара (по id): код 1С у привязанных, прежнее
     * значение у остальных (null с --clear-unlinked).
     *
     * @param  Collection<int, array{product: Product, status: OneCLinkStatus, external_id: ?string, trade_house_name: string, onec_name: string}>  $results
     * @return Collection<int, ?string>
     */
    private function finalExternalIds(Collection $results): Collection
    {
        $linkedProductIds = $results->pluck('product.id')->all();
        $final = $results->mapWithKeys(fn (array $result): array => [
            $result['product']->id => $result['status']->isLinked()
                ? $result['external_id']
                : ($this->option('clear-unlinked') ? null : $result['product']->external_id),
        ]);

        // Товары без sku в сопоставление не попали и остаются как есть.
        $untouched = Product::query()->whereNotIn('id', $linkedProductIds)->whereNotNull('external_id')->pluck('external_id', 'id');

        return $final->union($untouched);
    }

    /**
     * @param  Collection<int, array{product: Product, status: OneCLinkStatus, external_id: ?string, trade_house_name: string, onec_name: string}>  $results
     * @param  Collection<int, ?string>  $finalIds
     */
    private function report(Collection $results, Collection $finalIds): void
    {
        $this->components->twoColumnDetail('Товаров с sku', (string) $results->count());

        foreach (OneCLinkStatus::cases() as $status) {
            $this->components->twoColumnDetail($status->toString(), (string) $results->where('status', $status)->count());
        }

        $changed = $results->filter(fn (array $result): bool => $finalIds[$result['product']->id] !== $result['product']->external_id);
        $this->components->twoColumnDetail('external_id изменится у товаров', (string) $changed->count());
        $this->components->twoColumnDetail('…из них код 1С отличается от sku', (string) $changed->filter(fn (array $result): bool => $result['status'] === OneCLinkStatus::ByName)->count());

        $this->line('Примеры привязки по названию:');
        $this->table(['ID', 'Название на сайте', 'sku (ТД)', 'Код 1С'], $results->where('status', OneCLinkStatus::ByName)->take(8)->map(fn (array $result): array => [
            $result['product']->id,
            mb_strimwidth($result['product']->name, 0, 40, '…'),
            $result['product']->sku,
            $result['external_id'],
        ])->all());

        $problems = $results->reject(fn (array $result): bool => $result['status']->isLinked() || $result['status'] === OneCLinkStatus::Unknown);

        if ($problems->isNotEmpty()) {
            $this->line('Требуют ручного разбора (первые 10):');
            $this->table(['ID', 'Название на сайте', 'sku', 'Причина'], $problems->take(10)->map(fn (array $result): array => [
                $result['product']->id,
                mb_strimwidth($result['product']->name, 0, 40, '…'),
                $result['product']->sku,
                $result['status']->toString(),
            ])->all());
        }
    }

    /**
     * @param  Collection<int, array{product: Product, status: OneCLinkStatus, external_id: ?string, trade_house_name: string, onec_name: string}>  $results
     * @param  Collection<int, ?string>  $finalIds
     */
    private function writeReport(string $path, Collection $results, Collection $finalIds): void
    {
        $handle = fopen($path, 'w');

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['ID сайта', 'Название сайта', 'sku (код ТД)', 'Название ТД', 'Название 1С', 'Было external_id', 'Станет external_id', 'Результат'], ';', '"', '');

        foreach ($results as $result) {
            fputcsv($handle, [
                $result['product']->id,
                $result['product']->name,
                $result['product']->sku,
                $result['trade_house_name'],
                $result['onec_name'],
                $result['product']->external_id,
                $finalIds[$result['product']->id],
                $result['status']->toString(),
            ], ';', '"', '');
        }

        fclose($handle);

        $this->line("Отчёт по каждому товару: {$path}");
    }
}
