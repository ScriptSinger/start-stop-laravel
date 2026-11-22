<?php

namespace App\Services\Catalog;

/**
 * Стартовая таблица типов корпусов и скидок за сдачу старого АКБ. Загружается
 * командой trade-in:fill-types один раз; дальше типы, суммы и диапазоны правят
 * в админке (Каталог → Трейд-ин) — это единственный источник.
 */
class TradeInTypes
{
    /**
     * Допуск вокруг стандартных размеров, мм.
     */
    public const TOLERANCE = 2;

    /**
     * Строки таблицы: код, название для сайта, скидка, стандартные размеры
     * (длина, ширина, высота, мм); у грузовых размеров нет: тип берётся по коду в названии «Габаритов».
     *
     * @return list<array{code: string, name: string, amount: ?int, dimensions: ?array{int, int, int}}>
     */
    public static function rows(): array
    {
        return [
            ['code' => 'B19', 'name' => 'B19 - Азия до 40 Ah', 'amount' => 500, 'dimensions' => [187, 127, 227]],
            ['code' => 'B24', 'name' => 'B24 - Азия до 55 Ah', 'amount' => 700, 'dimensions' => [234, 127, 227]],
            ['code' => 'D20', 'name' => 'D20 - Азия до 60 Ah', 'amount' => 700, 'dimensions' => [208, 173, 225]],
            ['code' => 'D23', 'name' => 'D23 - Азия до 70 Ah', 'amount' => 1000, 'dimensions' => [230, 173, 225]],
            ['code' => 'D26', 'name' => 'D26 - Азия до 90 Ah', 'amount' => 1200, 'dimensions' => [260, 173, 225]],
            ['code' => 'D31', 'name' => 'D31 - Азия до 100 Ah', 'amount' => 1500, 'dimensions' => [306, 173, 225]],
            ['code' => 'L1', 'name' => 'L1 - Евро кубик', 'amount' => 700, 'dimensions' => [207, 175, 190]],
            ['code' => 'L2', 'name' => 'L2 - Евро до 65 Ah', 'amount' => 1000, 'dimensions' => [242, 175, 190]],
            ['code' => 'L3', 'name' => 'L3 - Евро до 77 Ah', 'amount' => 1200, 'dimensions' => [278, 175, 190]],
            ['code' => 'L4', 'name' => 'L4 - Евро до 85 Ah', 'amount' => 1300, 'dimensions' => [315, 175, 190]],
            ['code' => 'L5', 'name' => 'L5 - Евро до 105 Ah', 'amount' => 1500, 'dimensions' => [353, 175, 190]],
            ['code' => 'L6', 'name' => 'L6 - Евро до 110 Ah', 'amount' => 1500, 'dimensions' => [393, 175, 190]],
            ['code' => 'Груз A', 'name' => 'Груз A - 132-140 Ah', 'amount' => 1700, 'dimensions' => null],
            ['code' => 'Груз B', 'name' => 'Груз B - 180-220 Ah', 'amount' => 2000, 'dimensions' => null],
            ['code' => 'Груз C', 'name' => 'Груз C - 225-230 Ah', 'amount' => 2500, 'dimensions' => null],
            // Типы, которых нет в таблице скидок: заведены, чтобы тип корпуса у этих товаров не терялся
            // при очистке «Габаритов». Суммы нет — обмена нет, пока её не зададут в админке.
            ['code' => 'LB1', 'name' => 'LB1 - Евро низкий', 'amount' => null, 'dimensions' => [207, 175, 175]],
            ['code' => 'LB2', 'name' => 'LB2 - Евро низкий', 'amount' => null, 'dimensions' => [242, 175, 175]],
            ['code' => 'LB3', 'name' => 'LB3 - Евро низкий', 'amount' => null, 'dimensions' => [278, 175, 175]],
            ['code' => 'LB4', 'name' => 'LB4 - Евро низкий', 'amount' => null, 'dimensions' => [315, 175, 175]],
            ['code' => 'D33', 'name' => 'D33 - Азия', 'amount' => null, 'dimensions' => [324, 173, 225]],
            ['code' => 'E41', 'name' => 'E41 - Азия', 'amount' => null, 'dimensions' => [406, 173, 225]],
        ];
    }
}
