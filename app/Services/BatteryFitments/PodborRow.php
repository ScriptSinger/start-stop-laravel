<?php

namespace App\Services\BatteryFitments;

use Illuminate\Support\Carbon;

/**
 * Строка выгрузки подбора (podbor.xlsx, парсер akbmag.ru) → запись
 * battery_fitments.
 *
 * Колонки: A — «Аккумуляторы для …», B — марка, C — модель, D — поколение
 * (у моделей без поколений — модификация), E — двигатель, H–J — ёмкости,
 * K–M — полярности, N–P — габариты, T — тип клемм.
 *
 * Иерархия в файле: строка модели (только C), строка поколения (в D полное
 * имя «Марка Модель Поколение»), строки двигателей (в D короткое имя
 * поколения, в E — полное имя с двигателем). Марку и модель из имён убираем,
 * чтобы одно поколение не расходилось на «ВАЗ (Lada) Vesta I 2015 - 2022»
 * и «I 2015 - 2022».
 */
final class PodborRow
{
    /**
     * Тип клемм из колонки T → ключ (shop.battery_fitment.terminal_values).
     */
    private const TERMINALS = [
        // «Американский (клеммы УЗКИЕ под гайку…)» — раньше «узких».
        'под гайку' => 'threaded',
        'клеммы стандартные' => 'standard',
        'клеммы под конус' => 'standard',
        'клеммы узкие' => 'thin',
        'боковые клеммы' => 'side',
        'клеммы под болт' => 'bolt',
    ];

    /**
     * @param  array<string, string>  $cells
     * @return array{brand: string, model: string, generation: ?string, engine: ?string, capacity: ?string, polarity: ?string, dims: ?string, terminals: ?string, fixes: list<string>}|null null — пустая строка без марки и модели
     */
    public static function parse(array $cells): ?array
    {
        $cell = fn (string $column): string => self::clean($cells[$column] ?? '');
        $fixes = [];

        $title = $cell('A');
        $model = $cell('C');
        $brand = $cell('B');

        if (in_array($brand, ['', '0', '#NAME?'], true) && $model !== '') {
            // Сломанная формула в ячейке марки: «AC Cobra» → марка «AC».
            $brand = strtok($model, ' ');
            $fixes[] = 'brand';
        }

        if ($brand === '' || $model === '') {
            return null;
        }

        // Excel превратил «9-3» в дату 9 марта (число 45360) — вернём как было.
        if (preg_match('/^\d{5}$/', $model)) {
            $date = Carbon::create(1899, 12, 30)->addDays((int) $model);
            $candidate = $date->day.'-'.$date->month;

            if (str_contains($title, $brand.' '.$candidate)) {
                $model = $brand.' '.$candidate;
                $fixes[] = 'excel-date';
            }
        }

        $model = self::withoutPrefix($model, [$brand]);
        $generation = self::withoutPrefix($cell('D'), [$brand.' '.$model]);
        $engine = self::withoutPrefix($cell('E'), [$brand.' '.$model.' '.$generation]);

        $polarities = collect(['K', 'L', 'M'])
            ->map(fn (string $column): string => trim((string) preg_replace('/\[.*$/u', '', $cell($column))))
            ->filter()
            ->unique()
            ->values();

        return [
            'brand' => $brand,
            'model' => $model,
            'generation' => $generation === '' ? null : $generation,
            'engine' => $engine === '' ? null : $engine,
            'capacity' => self::joined($cell, ['H', 'I', 'J']),
            'polarity' => $polarities->isEmpty() ? null : $polarities->implode(', '),
            'dims' => self::joined($cell, ['N', 'O', 'P']),
            'terminals' => self::terminals($cell('T')),
            'fixes' => $fixes,
        ];
    }

    /**
     * Пробелы, переводы строк, узкий неразрывный пробел (в старой базе он
     * стал «?»: «2015?– 2018»), остатки HTML из парсера («<td>»).
     */
    private static function clean(string $value): string
    {
        $value = str_replace(['<td>', "\u{2009}", "\u{00A0}"], ['', ' ', ' '], $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * @param  list<string>  $prefixes
     */
    private static function withoutPrefix(string $value, array $prefixes): string
    {
        foreach ($prefixes as $prefix) {
            if ($prefix !== '' && mb_stripos($value, $prefix.' ') === 0) {
                return trim(mb_substr($value, mb_strlen($prefix)));
            }
        }

        return $value;
    }

    /**
     * @param  callable(string): string  $cell
     * @param  list<string>  $columns
     */
    private static function joined(callable $cell, array $columns): ?string
    {
        $values = array_values(array_filter(array_map($cell, $columns), fn (string $value): bool => $value !== ''));

        return $values === [] ? null : implode(', ', $values);
    }

    private static function terminals(string $value): ?string
    {
        foreach (self::TERMINALS as $needle => $key) {
            if (mb_stripos($value, $needle) !== false) {
                return $key;
            }
        }

        return null;
    }
}
