<?php

namespace App\Services\Supplier;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

/**
 * Загрузка прайса поставщика: обновляет его предложения и пересчитывает
 * «под заказ» у товаров. Пробный прогон — та же работа в транзакции,
 * которая откатывается, поэтому отчёт всегда совпадает с реальной записью.
 *
 * Позиции, которых нет в новом прайсе, считаются снятыми с продажи (остаток 0,
 * из прайса исключены). Названия и привязки к товарам уже загруженных
 * позиций не меняются.
 */
class SupplierPriceImporter
{
    public function __construct(
        private SupplierPriceReader $reader,
        private SupplierStockSync $sync,
    ) {}

    public function run(Supplier $supplier, string $path, bool $apply): SupplierImportReport
    {
        DB::beginTransaction();

        try {
            $report = $this->import($supplier, $path);
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        $apply ? DB::commit() : DB::rollBack();

        return $report;
    }

    private function import(Supplier $supplier, string $path): SupplierImportReport
    {
        $now = now();
        $existing = $supplier->offers()->get()->keyBy('supplier_code');
        $productsByName = $this->productsByName();

        $seen = [];
        $rows = $duplicates = $created = $costChanged = $linkedByName = 0;
        $unlinked = collect();
        $costChanges = collect();

        foreach ($this->reader->offers($supplier->format, $path) as $row) {
            $rows++;

            if (isset($seen[$row['code']])) {
                $duplicates++;

                continue;
            }

            $seen[$row['code']] = true;
            $offer = $existing->get($row['code']);

            if ($offer === null) {
                $productId = $productsByName[SupplierPriceReader::normalizeName($row['name'])] ?? null;
                $linkedByName += $productId !== null ? 1 : 0;

                $offer = $supplier->offers()->create([
                    'supplier_code' => $row['code'],
                    'name' => $row['name'],
                    'cost' => $row['cost'],
                    'quantity' => $row['quantity'],
                    'product_id' => $productId,
                    'in_price' => true,
                    'seen_at' => $now,
                ]);
                $created++;
            } else {
                if ((float) $offer->cost !== $row['cost']) {
                    $costChanged++;
                    $costChanges->push(['name' => $offer->name, 'old' => (float) $offer->cost, 'new' => $row['cost']]);
                }

                $offer->update(['cost' => $row['cost'], 'quantity' => $row['quantity'], 'in_price' => true, 'seen_at' => $now]);
            }

            if ($offer->product_id === null) {
                $unlinked->push(['code' => $row['code'], 'name' => $row['name'], 'cost' => $row['cost']]);
            }
        }

        $vanished = $supplier->offers()
            ->where('in_price', true)
            ->whereNotIn('supplier_code', array_keys($seen))
            ->update(['in_price' => false, 'quantity' => 0]);

        $synced = $this->sync->sync();

        return new SupplierImportReport(
            rows: $rows,
            duplicates: $duplicates,
            created: $created,
            costChanged: $costChanged,
            vanished: $vanished,
            linkedByName: $linkedByName,
            unlinked: $unlinked,
            costChanges: $costChanges,
            productsAdded: $synced['added'],
            productsRepriced: $synced['repriced'],
            productsCleared: $synced['cleared'],
        );
    }

    /**
     * Название товара → id, только для названий, которые на сайте встречаются
     * один раз: неоднозначные не привязываем.
     *
     * @return array<string, int>
     */
    private function productsByName(): array
    {
        return Product::query()->get(['id', 'name'])
            ->groupBy(fn (Product $product): string => SupplierPriceReader::normalizeName($product->name))
            ->filter(fn ($group): bool => $group->count() === 1)
            ->map(fn ($group): int => $group->first()->id)
            ->all();
    }
}
