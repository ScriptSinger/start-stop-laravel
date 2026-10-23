<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\SupplierOffer;
use App\Services\Import\XlsxReader;
use App\Services\Supplier\SupplierPriceReader;
use App\Services\Supplier\SupplierStockSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('suppliers:link-mediator {file : «Общий файл посредник.xlsx»} {--force : Записать в базу; без флага — только отчёт}')]
#[Description('Разово привязать предложения поставщиков к товарам по колонке «Код» (= sku) из файла-посредника')]
class LinkSupplierOffersFromMediator extends Command
{
    /**
     * Из файла берутся только соответствия «код поставщика или название →
     * sku»; цены и наценки из него не используются. Привязываются ещё не
     * привязанные предложения, у которых товар с таким sku на сайте один.
     * Перед запуском: php artisan db:backup.
     */
    public function handle(XlsxReader $xlsx, SupplierStockSync $sync): int
    {
        $skuByKey = [];

        foreach ([1, 2] as $sheet) {
            foreach ($xlsx->rows($this->argument('file'), $sheet) as $row) {
                $sku = trim($row['I'] ?? '');

                if (! ctype_digit($sku)) {
                    continue;
                }

                $code = trim($row['B'] ?? '');
                $key = str_starts_with($code, 'УТ-') ? $code : SupplierPriceReader::normalizeName($row['C'] ?? '');

                if ($key !== '') {
                    $skuByKey[$key] = $sku;
                }
            }
        }

        $productsBySku = Product::query()->whereIn('sku', array_unique($skuByKey))->get(['id', 'sku'])->groupBy('sku');

        $linked = $ambiguous = $missing = 0;

        DB::beginTransaction();

        foreach (SupplierOffer::query()->whereNull('product_id')->whereIn('supplier_code', array_keys($skuByKey))->get() as $offer) {
            $products = $productsBySku->get($skuByKey[$offer->supplier_code]);

            if ($products === null) {
                $missing++;
            } elseif ($products->count() > 1) {
                $ambiguous++;
            } else {
                $offer->update(['product_id' => $products->first()->id]);
                $linked++;
            }
        }

        $synced = $sync->sync();

        $this->table(['Показатель', 'Число'], [
            ['Соответствий в файле', count($skuByKey)],
            ['Привязано предложений', $linked],
            ['Нет товара с таким sku', $missing],
            ['Несколько товаров с одним sku', $ambiguous],
            ['Товаров под заказ после пересчёта', $synced['on_order']],
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
}
