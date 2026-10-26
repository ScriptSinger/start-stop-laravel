<?php

namespace App\Services\Supplier;

/**
 * Приводит название аккумулятора из прайса поставщика к виду номенклатуры 1С:
 * «Аккумулятор AC/DC 60Ah о.п. 510A L2L 242x175x190».
 *
 * Порядок частей: бренд и серия, ёмкость, полярность, пусковой ток, АЗИЯ,
 * типоразмер, «низкий/конус/болт», габариты. Ничего не выдумывается: чего в
 * прайсе нет (ток, габариты, полярность), того нет и в названии, а в ответе это
 * перечислено в missing. Что из исходной строки не вошло — в dropped.
 * Исключение: если полярность не указана, она принимается обратной (о.п.),
 * об этом остаётся пометка в notes.
 */
class BatteryNameFormatter
{
    /** Не легковые АКБ: ИБП, зарядные, химия и прочее. */
    private const NOT_A_BATTERY = '/VRLA|security|Terminal|\bNP\b|\bGEL\s*12|\b12\s*v\b|зарядн|зар\.|\bЗУ\b|очиститель|масл|\bСК\b|тестер|шампун|\d\s*(?:кг|мл|л)\b/iu';

    /**
     * @return array{name: string, missing: list<string>, dropped: list<string>, notes: list<string>}|null null — это не аккумулятор
     */
    public function format(string $source, ?int $cca = null, ?string $dimensions = null): ?array
    {
        $text = $this->clean($source);

        if (preg_match(self::NOT_A_BATTERY, $text) === 1) {
            return null;
        }

        $dropped = [];
        $notes = [];
        $isMoto = preg_match('/^(?:аккумулятор\s+)?мото\b/iu', $text) === 1;
        $text = trim((string) preg_replace('/^аккумулятор\s+/iu', '', $text));
        $text = trim((string) preg_replace('/^мото\s+/iu', '', $text));

        $capacity = $this->takeCapacity($text, $brandPart, $tail);

        if ($capacity === null) {
            return null;
        }

        $polarity = $this->takePolarity($text, $brandPart, $tail, $notes);

        // Азиатский типоразмер оканчивается на L или R: L — обратная, R — прямая (как в прайсах СибЭлектроИсточника).
        if ($polarity === null && ! in_array('в прайсе обе полярности (оп/пп)', $notes, true) && preg_match('/(?<![\p{L}\d])\d{2,3}[A-Z]\d{2}([LR])(?![\p{L}\d])/', $text, $size) === 1) {
            $polarity = $this->noted($size[1] === 'L' ? 'о.п.' : 'п.п.', "полярность взята из типоразмера (…{$size[1]})", $notes);
        }

        // Полярность не указана вовсе (а не «оп/пп»): по правилам магазина считаем обратной.
        if ($polarity === null && ! in_array('в прайсе обе полярности (оп/пп)', $notes, true)) {
            $polarity = 'о.п.';
            $notes[] = 'полярность не указана — принята о.п.';
        }
        $cca ??= $this->takeCca($text);
        $dimensions ??= $this->takeDimensions($text);
        $isAsia = false;
        $brand = $this->brand($brandPart, $isAsia);

        $codes = [];
        $flags = [];

        // «SOLITE 75D23L»: типоразмер в 1С стоит после тока, а не в бренде.
        if (preg_match_all('/(?<![\p{L}\d])\d{2,3}[A-Z]\d{2}[LR]?[A-Z]{0,2}(?![\p{L}\d])/', $brand, $sizes) > 0) {
            $codes = $sizes[0];
            $brand = trim((string) preg_replace('/\s+/u', ' ', str_replace($sizes[0], '', $brand)));
        }

        // «Zubr Premium низкий»: слово о корпусе не часть бренда.
        if (preg_match('/^(?<brand>.+?)\s+(?:низкий|низк|низ)$/iu', $brand, $low) === 1) {
            $brand = $low['brand'];
            $flags['низкий'] = true;
        }
        $truck = null;

        foreach ($this->tailTokens($tail, $text) as $token) {
            $lower = mb_strtolower($token);

            if (in_array($lower, ['азия', 'asia', 'jis'], true)) {
                $isAsia = true;
            } elseif (in_array($lower, ['низк', 'низкий', 'нижн', 'низ'], true)) {
                $flags['низкий'] = true;
            } elseif (in_array($lower, ['конус', 'болт', 'резьба'], true)) {
                $flags[$lower] = true;
            } elseif (preg_match('/^([ABC])\.\d$/', $token, $match) === 1) {
                $truck = "Груз {$match[1]}";
                $notes[] = "типоразмер {$token} записан как {$truck}";
            } elseif (preg_match('/^(?:L|LB)(\d)\.([01])$/', $token, $match) === 1) {
                $codes[] = mb_substr($token, 0, -3).$match[1].($match[2] === '0' ? 'L' : 'R');
                $notes[] = "типоразмер {$token} записан как ".end($codes);
            } elseif ($this->looksLikeSizeCode($token)) {
                $codes[] = $token;
            } else {
                $dropped[] = $token;
            }
        }

        $missing = [];

        foreach ([$polarity === null ? 'полярность' : null, $cca === null ? 'пусковой ток' : null, $dimensions === null ? 'габариты' : null] as $gap) {
            if ($gap !== null) {
                $missing[] = $gap;
            }
        }

        $parts = array_filter([
            'Аккумулятор',
            $isMoto ? 'Мото' : null,
            $brand,
            $this->number($capacity).'Ah',
            $polarity,
            $cca !== null ? "{$cca}A" : null,
            $isAsia ? 'АЗИЯ' : null,
            $truck,
            ...array_unique($codes),
            ...array_keys($flags),
            $dimensions,
        ], fn (?string $part): bool => $part !== null && $part !== '');

        return ['name' => implode(' ', $parts), 'missing' => $missing, 'dropped' => $dropped, 'notes' => $notes];
    }

    private function clean(string $name): string
    {
        $name = str_replace(["\u{a0}", 'ё', 'Ё'], [' ', 'е', 'Е'], $name);
        // «306х173х200» с русской «х» и «×» → «x»
        $name = (string) preg_replace('/(?<=\d)\s*[хХxX×*]\s*(?=\d)/u', 'x', $name);
        // Латинская «o» в «o.п.» вместо русской «о»
        $name = (string) preg_replace('/(?<![\p{L}\d])[oO](?=\.?\s*п\.)/u', 'о', $name);

        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    /**
     * Ёмкость и «хвост» строки после неё. $head — часть до ёмкости (бренд).
     */
    private function takeCapacity(string $text, ?string &$head, ?string &$tail): ?float
    {
        $patterns = [
            // 6ст-100 Ач, 6СТ-63.0, 6СТ 60
            '/^(?<head>.*?)\s*6\s*[сc][тt]\s*-?\s*(?<cap>\d{1,3}(?:[.,]\d)?)(?:\s*(?:Ач|Ah))?(?<tail>.*)$/iu',
            // 100Ah, 14Ач, (7,5Ah)
            '/^(?<head>.*?)[\s(]*(?<cap>\d{1,3}(?:[.,]\d)?)\s*(?:Ач|Ah)\b\)?(?<tail>.*)$/iu',
            // «6ст - 60 Zubr Ultra»: ёмкость в начале, бренд после неё
            '/^6\s*[сc][тt]\s*-\s*(?<cap>\d{1,3})\s+(?<tail>.*)$/iu',
            // «XTREME +EFB 100 обр (…)», «TOPLA EFB STOP&GO 105 JIS»
            '/^(?<head>.*?)\s+(?<cap>\d{2,3})(?=\s+(?:обр|пр|уни|JIS|[01]\s*\(|\())(?<tail>.*)$/iu',
            // «Аккумулятор UNICUM 60 оп/пп», «ПУЛЬС 190 конус/болт»: бренд без цифр и ёмкость после него
            '/^(?<head>[^\d]+?)\s+(?<cap>\d{2,3})(?=\s|$)(?<tail>.*)$/iu',
        ];

        foreach ($patterns as $index => $pattern) {
            if (preg_match($pattern, $text, $match) !== 1) {
                continue;
            }

            $capacity = (float) str_replace(',', '.', $match['cap']);

            if ($capacity < 1 || $capacity > 300) {
                continue;
            }

            $head = $index === 2 ? trim($match['tail']) : trim($match['head'], ' ,');
            $tail = $index === 2 ? '' : trim($match['tail']);

            // «HCZ20H-i (YT20-4, 22Ah)»: слова в скобке до ёмкости — это уже не бренд, а коды.
            if (preg_match('/^(?<brand>.*?)\s*\((?<inside>[^()]*)$/u', $head, $inner) === 1) {
                $head = trim($inner['brand']);
                $tail = trim($inner['inside'].' '.$tail);
            }

            // «6ст - 60 Zubr Ultra»: перед ёмкостью ничего нет, бренд идёт после неё.
            if ($head === '' && $tail !== '') {
                [$head, $tail] = [$tail, ''];
            }

            return $capacity;
        }

        return null;
    }

    /**
     * @param  list<string>  $notes
     */
    private function takePolarity(string $text, ?string &$head, ?string &$tail, array &$notes): ?string
    {
        $haystack = ' '.$tail.' ';
        // Слово «обр» стоит между ёмкостью и скобкой, поэтому ищем и в хвосте, и в целой строке.
        $both = ' '.$text.' ';

        return match (true) {
            preg_match('/(?<![\p{L}\d])(?:оп\/пп|пп\/оп)(?![\p{L}\d])/iu', $both) === 1 => $this->noted(null, 'в прайсе обе полярности (оп/пп)', $notes),
            preg_match('/(?<![\p{L}\d])о\.?\s*п\.?(?![\p{L}\d])|(?<![\p{L}\d])обр(?![\p{L}\d])/iu', $both) === 1 => 'о.п.',
            preg_match('/(?<![\p{L}\d])п\.\s*п\.?|(?<![\p{L}\d])пр(?![\p{L}\d])/iu', $both) === 1 => 'п.п.',
            preg_match('/\((?:L)\+\)/i', $both) === 1 => $this->noted('п.п.', '(L+) записано как п.п.', $notes),
            preg_match('/\((?:R)\+\)/i', $both) === 1 => $this->noted('о.п.', '(R+) записано как о.п.', $notes),
            default => null,
        };
    }

    /**
     * @param  list<string>  $notes
     */
    private function noted(?string $value, string $note, array &$notes): ?string
    {
        $notes[] = $note;

        return $value;
    }

    private function takeCca(string $text): ?int
    {
        if (preg_match('/пус[кк]?\.?\s*ток\.?\s*(\d{2,4})/iu', $text, $match) === 1 || preg_match('/(?<![\d.x])(\d{3,4})\s*[AА](?![\p{L}\d])/u', $text, $match) === 1) {
            return (int) $match[1];
        }

        return null;
    }

    private function takeDimensions(string $text): ?string
    {
        return preg_match('/(\d{2,3})x(\d{2,3})x(\d{2,3})/', $text, $match) === 1 ? "{$match[1]}x{$match[2]}x{$match[3]}" : null;
    }

    private function brand(string $head, bool &$isAsia): string
    {
        $head = (string) preg_replace('/[«»"]/u', '', $head);
        $head = (string) preg_replace('/\s*[01]\s*\((?:R|L)\+\)/i', '', $head);

        if (preg_match('/(?<![\p{L}\d])(?:asia|азия|jis)(?![\p{L}\d])/iu', $head) === 1) {
            $isAsia = true;
            $head = (string) preg_replace('/(?<![\p{L}\d])(?:asia|азия|jis)(?![\p{L}\d])/iu', '', $head);
        }

        return trim((string) preg_replace('/\s+/u', ' ', $head), ' ,-');
    }

    /**
     * Слова после ёмкости, без пусковых токов, габаритов и полярности.
     *
     * @return list<string>
     */
    private function tailTokens(string $tail, string $whole): array
    {
        $tail = (string) preg_replace('/пус[кк]?\.?\s*ток\.?\s*\d{2,4}\s*,?/iu', ' ', $tail);
        $tail = (string) preg_replace('/раз(?:\.|м\.?)?\s*/iu', ' ', $tail);
        $tail = (string) preg_replace('/(?<![\p{L}\d])\d{3,4}\s*[AА](?![\p{L}\d])/u', ' ', $tail);
        $tail = (string) preg_replace('/\d{2,3}x\d{2,3}x\d{2,3}(?:[x\/]\d{2,3})*/', ' ', $tail);
        // «п.п.75L3.1» — полярность слита с кодом: разделяем пробелом
        $tail = (string) preg_replace('/((?<![\p{L}\d])[оп]\.\s*п\.)(?=[\p{L}\d])/iu', '$1 ', $tail);
        $tail = (string) preg_replace('/(?<![\p{L}\d])(?:о\.?\s*п\.?|п\.\s*п\.?|обр|пр|уни)(?![\p{L}\d])/iu', ' ', $tail);
        $tail = (string) preg_replace('/\b[01]\s*\((?:R|L)\+\)/i', ' ', $tail);
        $tail = (string) preg_replace('/[(),;]/u', ' ', $tail);

        return array_values(array_filter(array_map(fn (string $token): string => trim($token, '.'), preg_split('/\s+/u', trim($tail)) ?: []), fn (string $token): bool => $token !== '' && $token !== '-'));
    }

    private function looksLikeSizeCode(string $token): bool
    {
        return preg_match('/\p{L}/u', $token) === 1 && preg_match('/\d/', $token) === 1 && mb_strlen($token) >= 3;
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }
}
