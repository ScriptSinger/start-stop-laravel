<?php

namespace App\Services\Supplier;

use App\Models\Supplier;

/**
 * Розничная цена под заказ: закупка плюс наценка поставщика, округлённая
 * вверх до 50 ₽.
 */
class SupplierPricing
{
    private const STEP = 50;

    public static function retailPrice(float $cost, float $markupPercent): float
    {
        return ceil(round($cost * (1 + $markupPercent / 100), 2) / self::STEP) * self::STEP;
    }

    public static function forSupplier(Supplier $supplier, float $cost): float
    {
        return self::retailPrice($cost, (float) $supplier->markup_percent);
    }
}
