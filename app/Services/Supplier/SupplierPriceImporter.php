<?php

namespace App\Services\Supplier;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use Illuminate\Support\Facades\DB;

/**
 * Загрузка прайса поставщика: обновляет его предложения и пересчитывает
 * «под заказ» у товаров. Пробный прогон рассчитывает изменения без записи.
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
        return $apply
            ? DB::transaction(fn (): SupplierImportReport => $this->import($supplier, $path, true))
            : $this->import($supplier, $path, false);
    }

    private function import(Supplier $supplier, string $path, bool $apply): SupplierImportReport
    {
        $now = now();
        $existing = $supplier->offers()->get()->keyBy('supplier_code');
        $productsByName = $this->productsByName();

        $seen = [];
        $rows = $duplicates = $created = $costChanged = $linkedByName = 0;
        $unlinked = collect();
        $costChanges = collect();
        $previewOffers = collect();

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

                $attributes = [
                    'supplier_code' => $row['code'],
                    'name' => $row['name'],
                    'cost' => $row['cost'],
                    'quantity' => $row['quantity'],
                    'product_id' => $productId,
                    'in_price' => true,
                    'seen_at' => $now,
                ];
                $offer = $apply ? $supplier->offers()->create($attributes) : new SupplierOffer($attributes);
                $offer->supplier_id = $supplier->id;
                $created++;
            } else {
                if ((float) $offer->cost !== $row['cost']) {
                    $costChanged++;
                    $costChanges->push(['name' => $offer->name, 'old' => (float) $offer->cost, 'new' => $row['cost']]);
                }

                $attributes = ['cost' => $row['cost'], 'quantity' => $row['quantity'], 'in_price' => true, 'seen_at' => $now];
                $apply ? $offer->update($attributes) : $offer->fill($attributes);
            }

            if (! $apply) {
                $offer->setRelation('supplier', $supplier);
                $previewOffers->push($offer);
            }

            if ($offer->product_id === null) {
                $unlinked->push(['code' => $row['code'], 'name' => $row['name'], 'cost' => $row['cost']]);
            }
        }

        $vanishedQuery = $supplier->offers()
            ->where('in_price', true)
            ->whereNotIn('supplier_code', array_keys($seen));
        $vanished = $apply ? $vanishedQuery->update(['in_price' => false, 'quantity' => 0]) : $vanishedQuery->count();

        $synced = $apply ? $this->sync->sync() : $this->sync->sync($previewOffers, $supplier->id);

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
