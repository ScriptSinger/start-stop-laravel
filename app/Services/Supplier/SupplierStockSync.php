<?php

namespace App\Services\Supplier;

use App\Models\Product;
use App\Models\SupplierOffer;
use Illuminate\Support\Collection;

/**
 * Переносит лучшие предложения поставщиков в поля «под заказ» товара
 * (supplier_quantity, supplier_price, supplier_id), которые уже читает витрина.
 *
 * Предложение подходит, если оно есть в последнем прайсе, поставщик активен
 * и либо отмечено «не учитывать количество», либо остаток не меньше порога
 * поставщика. Из подходящих берётся самая низкая розничная цена.
 * Цена и остаток 1С не трогаются: при своём остатке витрина показывает их.
 *
 * Витрина считает «под заказ» от общего порога shop.supplier_order_min_quantity,
 * поэтому в supplier_quantity пишется не меньше этого порога. Реальный
 * остаток поставщика покупателю не показывается.
 */
class SupplierStockSync
{
    /**
     * on_order — сколько товаров под заказ после пересчёта (всего); added, repriced и
     * cleared — что изменилось по сравнению с текущим состоянием сайта.
     *
     * @return array{on_order: int, added: int, repriced: int, cleared: int}
     */
    public function sync(?Collection $previewOffers = null, ?int $previewSupplierId = null): array
    {
        $minimum = (int) config('shop.supplier_order_min_quantity');

        $offers = SupplierOffer::query()
            ->whereNotNull('product_id')
            ->where('in_price', true)
            ->whereHas('supplier', fn ($suppliers) => $suppliers->where('is_active', true))
            ->when($previewSupplierId !== null, fn ($query) => $query->where('supplier_id', '!=', $previewSupplierId))
            ->with('supplier')
            ->orderBy('id')
            ->get();

        if ($previewOffers !== null) {
            $offers = $offers->concat($previewOffers->filter(fn (SupplierOffer $offer): bool => $offer->product_id !== null && $offer->in_price && $offer->supplier->is_active));
        }

        $best = $offers
            ->filter(fn (SupplierOffer $offer): bool => $this->qualifies($offer))
            ->groupBy('product_id')
            ->map(fn (Collection $offers): SupplierOffer => $offers
                ->sortBy([
                    fn (SupplierOffer $a, SupplierOffer $b): int => $this->retail($a) <=> $this->retail($b),
                    fn (SupplierOffer $a, SupplierOffer $b): int => $a->supplier->lead_working_days <=> $b->supplier->lead_working_days,
                    fn (SupplierOffer $a, SupplierOffer $b): int => ($a->id ?? PHP_INT_MAX) <=> ($b->id ?? PHP_INT_MAX),
                ])
                ->first());

        $before = Product::query()->whereIn('id', $best->keys())->get(['id', 'supplier_id', 'supplier_price'])->keyBy('id');
        $added = $repriced = 0;

        foreach ($best as $productId => $offer) {
            $retail = $this->retail($offer);
            $previous = $before->get($productId);

            if ($previous === null) {
                continue;
            }

            if ($previous->supplier_id === null) {
                $added++;
            } elseif ($previous->supplier_id !== $offer->supplier_id || (float) $previous->supplier_price !== $retail) {
                $repriced++;
            }

            if ($previewOffers === null) {
                Product::query()->whereKey($productId)->update([
                    'supplier_quantity' => max((int) $offer->quantity, $minimum),
                    'supplier_price' => $retail,
                    'supplier_id' => $offer->supplier_id,
                ]);
            }
        }

        $toClear = Product::query()
            ->whereNotNull('supplier_id')
            ->whereNotIn('id', $best->keys());
        $cleared = $previewOffers === null
            ? $toClear->update(['supplier_quantity' => 0, 'supplier_price' => null, 'supplier_id' => null])
            : $toClear->count();

        return ['on_order' => $before->count(), 'added' => $added, 'repriced' => $repriced, 'cleared' => $cleared];
    }

    private function qualifies(SupplierOffer $offer): bool
    {
        $supplier = $offer->supplier;

        return $supplier->ignore_quantity || (int) $offer->quantity >= $supplier->min_quantity;
    }

    private function retail(SupplierOffer $offer): float
    {
        return SupplierPricing::forSupplier($offer->supplier, (float) $offer->cost);
    }
}
