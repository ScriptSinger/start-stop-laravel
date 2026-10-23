<?php

namespace App\Services\Supplier;

use Carbon\CarbonImmutable;

/**
 * Срок поставки под заказ в рабочих днях. В выходные поставщики товар не
 * возят: заказ в субботу или воскресенье считается как заказ в пятницу
 * (пятница + 2 рабочих дня = вторник, суббота + 2 = тоже вторник).
 * Праздники не учитываются.
 */
class SupplierDelivery
{
    public static function expectedDate(CarbonImmutable $orderedAt, int $workingDays): CarbonImmutable
    {
        $date = $orderedAt->startOfDay();

        if ($date->isWeekend()) {
            $date = $date->previous('Friday');
        }

        for ($left = max($workingDays, 0); $left > 0; $left--) {
            $date = $date->addDay();

            while ($date->isWeekend()) {
                $date = $date->addDay();
            }
        }

        return $date;
    }

    /**
     * «28 октября» — для витрины.
     */
    public static function format(CarbonImmutable $date): string
    {
        return $date->locale('ru')->isoFormat('D MMMM');
    }
}
