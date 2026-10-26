<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use App\Services\Supplier\SupplierPriceReader;
use App\Services\Supplier\SupplierStockSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('suppliers:apply-links {file : CSV с сопоставлением (колонки «Поставщик», «Код у поставщика», «Название в прайсе», «ID на сайте»)} {--force : Записать в базу; без флага — только отчёт}')]
#[Description('Привязать загруженные предложения поставщиков к товарам сайта по файлу сопоставления')]
class LinkSupplierOffersFromFile extends Command
{
    /**
     * Предложение находится по поставщику и ключу позиции: код у поставщика
     * (АКБ АВТО, «УТ-…»), а если кода нет — нормализованное название из прайса.
     * Уже привязанные к другому товару предложения не перезаписываются, а
     * предложения, которых ещё нет в базе (прайс не загружен), пропускаются.
     * Без --force выполняется то же самое в транзакции, которая откатывается.
     * Перед записью: php artisan db:backup.
     */
    public function handle(SupplierStockSync $sync): int
    {
        $rows = $this->readRows($this->argument('file'));

        if ($rows === null) {
            return self::FAILURE;
        }

        $suppliers = Supplier::query()->pluck('id', 'name');
        $counters = ['linked' => 0, 'already' => 0, 'other_product' => 0, 'no_offer' => 0, 'no_product' => 0, 'no_supplier' => 0, 'ambiguous' => 0];

        DB::beginTransaction();

        $targets = [];

        foreach ($rows as $row) {
            $supplierId = $suppliers->get($row['supplier']);

            if ($supplierId === null) {
                $counters['no_supplier']++;

                continue;
            }

            $key = $row['code'] !== '' ? $row['code'] : SupplierPriceReader::normalizeName($row['name']);
            $targets[$supplierId.'|'.$key][$row['product_id']] = $row;
        }

        foreach ($targets as $key => $products) {
            [$supplierId, $code] = explode('|', $key, 2);

            // Одна позиция прайса, два разных товара (например, «оп/пп»): однозначно привязать нельзя.
            if (count($products) > 1) {
                $counters['ambiguous']++;

                continue;
            }

            $productId = (int) array_key_first($products);
            $offer = SupplierOffer::query()->where('supplier_id', $supplierId)->where('supplier_code', $code)->first();

            if ($offer === null) {
                $counters['no_offer']++;
            } elseif (! Product::query()->whereKey($productId)->exists()) {
                $counters['no_product']++;
            } elseif ($offer->product_id === $productId) {
                $counters['already']++;
            } elseif ($offer->product_id !== null) {
                $counters['other_product']++;
            } else {
                $offer->update(['product_id' => $productId]);
                $counters['linked']++;
            }
        }

        $synced = $sync->sync();

        $this->table(['Показатель', 'Число'], [
            ['Строк в файле', count($rows)],
            ['Привязано', $counters['linked']],
            ['Уже были привязаны к этому товару', $counters['already']],
            ['Привязаны к другому товару (не тронуты)', $counters['other_product']],
            ['Одна позиция — несколько товаров (пропущено)', $counters['ambiguous']],
            ['Нет предложения в базе (прайс не загружен)', $counters['no_offer']],
            ['Нет товара с таким ID', $counters['no_product']],
            ['Нет такого поставщика', $counters['no_supplier']],
            ['Станет «под заказ» (новых товаров)', $synced['added']],
            ['Изменится цена или поставщик под заказ', $synced['repriced']],
            ['Перестанет быть «под заказ»', $synced['cleared']],
            ['Всего «под заказ» после пересчёта', $synced['on_order']],
        ]);

        if (! $this->option('force')) {
            DB::rollBack();
            $this->info('Пробный прогон: в базу ничего не записано. Для записи добавьте --force.');

            return self::SUCCESS;
        }

        DB::commit();
        $this->info('Привязки записаны.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{supplier: string, code: string, name: string, product_id: string}>|null
     */
    private function readRows(string $path): ?array
    {
        $handle = @fopen($path, 'r');

        if ($handle === false) {
            $this->error("Не удалось открыть {$path}.");

            return null;
        }

        $header = array_map(fn (?string $title): string => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $title)), (array) fgetcsv($handle, 0, ';', '"', ''));
        $columns = array_flip($header);

        foreach (['Поставщик', 'Код у поставщика', 'Название в прайсе', 'ID на сайте'] as $required) {
            if (! isset($columns[$required])) {
                $this->error("В файле нет колонки «{$required}».");

                return null;
            }
        }

        $rows = [];

        while (($cells = fgetcsv($handle, 0, ';', '"', '')) !== false) {
            $productId = trim((string) ($cells[$columns['ID на сайте']] ?? ''));

            if ($productId === '' || ! ctype_digit($productId)) {
                continue;
            }

            $rows[] = [
                'supplier' => trim((string) $cells[$columns['Поставщик']]),
                'code' => trim((string) ($cells[$columns['Код у поставщика']] ?? '')),
                'name' => (string) ($cells[$columns['Название в прайсе']] ?? ''),
                'product_id' => $productId,
            ];
        }

        fclose($handle);

        return $rows;
    }
}
