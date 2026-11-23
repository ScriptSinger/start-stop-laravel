<?php

namespace App\Services\Catalog;

use App\Models\ServicePrice;

/**
 * Таблица цен на обслуживание по ёмкости на странице «Обслуживание». Строится из
 * service_prices (админка → Сайт → Цены на обслуживание), поэтому вручную её набирать не нужно.
 *
 * На странице shop.service_prices.page_slug таблица выводится всегда: на месте метки
 * [[block:service-prices]], на месте прежней ручной таблицы «Ёмкость аккумулятора» или, если
 * ни того ни другого в тексте нет, в конце текста. На остальных страницах работает только метка.
 */
class ServicePriceTable
{
    private const MARKER = '~(?:<p>\s*)?\[\[block:service-prices\]\](?:\s*</p>)?~u';

    /** Прежняя ручная таблица: первая таблица с заголовком «Ёмкость аккумулятора». */
    private const LEGACY_TABLE = '~<table\b(?:(?!</table>).)*?Ёмкость\s+аккумулятора.*?</table>~isu';

    public static function render(string $html, bool $always = false): string
    {
        $table = fn (): string => view('page.service-price-table', [
            'prices' => ServicePrice::query()->ordered()->get(),
        ])->render();

        if (preg_match(self::MARKER, $html) === 1) {
            return (string) preg_replace_callback(self::MARKER, $table, $html);
        }

        if (! $always) {
            return $html;
        }

        $replaced = preg_replace_callback(self::LEGACY_TABLE, $table, $html, 1, $count);

        return $count > 0 && $replaced !== null ? $replaced : $html.$table();
    }
}
