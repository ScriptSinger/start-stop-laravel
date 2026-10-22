<?php

namespace App\Services\Catalog;

/**
 * Скидка за сдачу старого аккумулятора по ёмкости (config shop.trade_in_by_capacity).
 */
class TradeInDiscount
{
    public function forCapacity(float $ampereHours): ?int
    {
        foreach (config('shop.trade_in_by_capacity') as $from => $discount) {
            if ($ampereHours >= $from) {
                return (int) $discount;
            }
        }

        return null;
    }
}
