<?php

namespace App\Services\Catalog;

use App\Models\TradeInType;

/**
 * Таблица скидок за сдачу старого АКБ на странице «Трейд-ин». Строится из активных типов
 * корпусов с заданной суммой — тех же данных, по которым считается скидка в корзине
 * (админка → Каталог → Трейд-ин), поэтому вручную её набирать не нужно.
 *
 * На странице «Трейд-ин» (shop.trade_in.page_slug) таблица выводится всегда: на месте
 * метки [[block:trade-in]], на месте прежней ручной таблицы «Тип АКБ» или, если ни того ни
 * другого в тексте нет, в конце текста. На остальных страницах работает только метка.
 */
class TradeInTable
{
    private const MARKER = '~(?:<p>\s*)?\[\[block:trade-in\]\](?:\s*</p>)?~u';

    /** Прежняя ручная таблица со скидками: первая таблица с заголовком «Тип АКБ». */
    private const LEGACY_TABLE = '~<table\b(?:(?!</table>).)*?Тип\s+АКБ.*?</table>~isu';

    public static function render(string $html, bool $always = false): string
    {
        $table = fn (): string => view('page.trade-in-table', [
            'types' => TradeInType::query()->active()->where('amount', '>', 0)->ordered()->get(),
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
