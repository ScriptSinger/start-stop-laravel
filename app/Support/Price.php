<?php

namespace App\Support;

/**
 * Формат цен витрины: «21 800 ₽» — целые рубли, неразрывные пробелы,
 * чтобы сумма не рвалась на две строки.
 */
final class Price
{
    public static function format(float|int|string|null $value): string
    {
        return number_format((float) $value, 0, '', "\u{00A0}")."\u{00A0}₽";
    }
}
