<?php

namespace App\Services\Import;

use Illuminate\Support\Str;

/**
 * Разбор строки прайса 1С в «карточку в стиле сайта»: короткое название,
 * код товара, раздел, производитель и характеристики. Ничего не пишет в базу.
 *
 * Аккумуляторы разбираются по названию 1С («Аккумулятор AKOM 65Ah о.п. 600A L2L
 * 242x175x190»), остальные товары берут название 1С как есть: у них оно такое же,
 * как на сайте. Раздел определяется по ключевым словам названия.
 */
class OneCProductSpecs
{
    /**
     * Ключевое слово в названии → название раздела на сайте. Первое совпадение.
     *
     * @var array<string, string>
     */
    private const CATEGORY_KEYWORDS = [
        '/^ф\.\s*масл|фильтр масл/iu' => 'Фильтры масляные',
        '/^ф\.\s*возд|фильтр\s+возд/iu' => 'Фильтры воздушные',
        '/^ф\.\s*салон|фильтр\s+салон/iu' => 'Фильтры салонные',
        '/^ф\.\s*топлив|фильтр\s+топлив/iu' => 'Фильтры топливные',
        '/болт\s+крепления\s+акб|крепление\s+акб/iu' => 'Крепление АКБ',
        '/автокресло/iu' => 'Автокресла',
        '/канистр/iu' => 'Канистры',
        '/огнетушител/iu' => 'Огнетушители',
        '/^краска/iu' => 'Краски и эмали',
        '/щетка\s+для\s+(?:чистки|очистки)/iu' => 'Щетки для чистки',
        '/чернитель/iu' => 'Полироли',
        '/жидкость\s+для\s+гур|power\s+steering/iu' => 'Технические жидкости',
        '/колодки|барабан\s+тормоз/iu' => 'Тормозные колодки',
        '/^(?:прокладка|ремень\s+приводн|опора|сайлентблок|ступица|подшипник|замок|клипса|рычаг|заглушка|крышка\s+бачка|ут?плотнитель|уплoтнитeль|фильтр\s+акпп|ф\.\s*акпп)/iu' => 'Прочие запчасти',
        '/газовый\s+баллон|набор\s+для\s+ремонта/iu' => 'Прочие автотовары',
        '/очиститель/iu' => 'Очистители',
        '/щетк[аи]\s+(?:стеклоочист|для\s+стекл)|стеклоочист/iu' => 'Стеклоочистители',
        '/щетк[аи]\s+для\s+снега|скребок/iu' => 'Щетки для снега',
        '/освежитель|ароматизатор/iu' => 'Ароматизаторы',
        '/полироль/iu' => 'Полироли',
        '/смазка/iu' => 'Смазки',
        '/шампунь/iu' => 'Автошампуни',
        '/промывк/iu' => 'Промывки',
        '/присадк|антигель|цетан|вода\s+дистилл|размораж|обезжирив|растворитель|преобразователь\s+ржавчин|мовиль|раскоксов|антизапотев|индикатор\s+утечек|жидкий\s+ключ/iu' => 'Технические жидкости',
        '/антифриз|тосол/iu' => 'Антифриз',
        '/жидкость\s+тормоз|тормозная\s+жидкость/iu' => 'Тормозные жидкости',
        '/трансмисс|\bатф\b|\batf\b/iu' => 'Трансмиссионное масло',
        '/моторное\s+масло|масло\s+моторн/iu' => 'Моторное масло',
        '/^лампа/iu' => 'Лампы',
        '/зарядн/iu' => 'Зарядные устройства',
        '/держатель/iu' => 'Для смартфона',
        '/кабель/iu' => 'Кабели',
        '/салфетк|губка/iu' => 'Салфетки',
        '/герметик/iu' => 'Герметики',
        '/шторк/iu' => 'Шторки',
        '/\bтрос\b/iu' => 'Тросы',
        '/компрессор/iu' => 'Компрессоры',
        '/пылесос/iu' => 'Пылесосы',
        '/клемм/iu' => 'Клеммы',
        '/предохранител/iu' => 'Предохранители',
        '/провода\s+(?:пусков|прикур)/iu' => 'Провода прикуривания',
        '/батарейк|элемент\s+питания/iu' => 'Батарейки',
        '/ареометр/iu' => 'Ареометры',
        '/домкрат|головка\s+торцев|отвертк|ключ\s|набор\s+(?:ключей|отверток|надфилей|съемников|экстракторов)|удлинитель\s+головок|захват\s+магнитн|надфил|трещетк|detool/iu' => 'Инструменты',
    ];

    /**
     * Разделы, которых может не быть на сайте: название => родитель (null —
     * верхний уровень). Создаются скрытыми: пустой включённый раздел попал бы
     * в меню шапки.
     *
     * @return array<string, string|null>
     */
    public static function newCategories(): array
    {
        return [
            'Автозапчасти' => null,
            'Фильтры воздушные' => 'Автозапчасти',
            'Фильтры салонные' => 'Автозапчасти',
            'Фильтры топливные' => 'Автозапчасти',
            'Тормозные колодки' => 'Автозапчасти',
            'Прочие запчасти' => 'Автозапчасти',
            'Автокресла' => 'Автотовары',
            'Канистры' => 'Автотовары',
            'Огнетушители' => 'Автотовары',
            'Краски и эмали' => 'Автотовары',
            'Щетки для чистки' => 'Автотовары',
            'Прочие автотовары' => 'Автотовары',
        ];
    }

    /**
     * @param  array{external_id: string, name: string, price: ?float, quantity: ?float}  $row
     * @param  string  $article  колонка «Артикул» прайса
     * @return array{external_id: string, source_name: string, name: string, code: ?string, sku: ?string, brand: ?string, category: ?string, price: float, quantity: int, attributes: array<string, string>, is_battery: bool, warnings: list<string>}
     */
    public function build(array $row, string $article = ''): array
    {
        $name = $this->clean($row['name']);
        $spec = [
            'external_id' => $row['external_id'],
            'source_name' => $name,
            'name' => $name,
            'code' => null,
            'sku' => trim($article) !== '' ? trim($article) : null,
            'brand' => null,
            'category' => $this->category($name),
            'price' => max(0.0, (float) ($row['price'] ?? 0)),
            'quantity' => max(0, (int) round($row['quantity'] ?? 0)),
            'attributes' => [],
            'is_battery' => false,
            'warnings' => [],
        ];

        if (preg_match('/^аккумулятор\s/iu', $name) === 1) {
            $spec = $this->battery($spec);
        }

        if ($spec['price'] <= 0) {
            $spec['warnings'][] = 'нет цены в 1С';
        }

        return $spec;
    }

    /**
     * Адрес, как у товаров сайта: «О.П.» → «o-p», «100Ah» остаётся «100ah», «щ» → «shch».
     */
    public function slug(string $name): string
    {
        return Str::slug(strtr(str_replace('.', ' ', $name), ['щ' => 'shch', 'Щ' => 'Shch']), '-', 'ru');
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    private function battery(array $spec): array
    {
        $title = (string) preg_replace('/^аккумулятор\s+/iu', '', $spec['source_name']);
        // «Мото BUSHIDO HCZ20H-i 22Ah …»: слово «Мото» — тип, бренд идёт следом.
        $motoPrefix = preg_match('/^мото\s+/iu', $title) === 1;
        $title = (string) preg_replace('/^мото\s+/iu', '', $title);

        if (preg_match('/^(?<head>.*?)\s*(?<cap>\d+(?:[.,]\d+)?)(?<alt>\(\d+\))?\s*(?:Ah|Ач)\b(?<tail>.*)$/iu', $title, $found) !== 1) {
            $spec['warnings'][] = 'не удалось разобрать ёмкость: название оставлено как в 1С';

            return $spec;
        }

        if (($found['alt'] ?? '') !== '') {
            $spec['warnings'][] = "в 1С две ёмкости: {$found['cap']}{$found['alt']}";
        }

        $tail = $found['tail'];
        $capacity = str_replace(',', '.', $found['cap']);
        $capacityLabel = str_replace('.', ',', $capacity);

        $polarity = match (true) {
            preg_match('/\bо\s*\.?\s*п\b/iu', $tail) === 1 => ['О.П.', 'Обратная'],
            preg_match('/\bп\s*\.?\s*п\b/iu', $tail) === 1 => ['П.П.', 'Прямая'],
            preg_match('/\bуни\b/iu', $tail) === 1 => ['уни', 'Универсальная'],
            default => [null, null],
        };

        $isTruck = preg_match('/груз/iu', $tail) === 1;
        $isMoto = ! $isTruck && ($motoPrefix || preg_match('/moto|мото/iu', $title) === 1 || preg_match('/\b(?:iGEL|[YU]T[XZ]\w*|HCX\w*)\b/u', $tail) === 1);
        $isVrla = preg_match('/vrla/iu', $found['head']) === 1;

        preg_match('/(\d{2,3})\s*[xх×]\s*(\d{2,3})\s*[xх×]\s*(\d{2,3})/u', $tail, $dims);
        preg_match('/(\d{3,4})\s*[AА]\b/u', $tail, $cca);
        preg_match('/\b(\d{2,3}[A-H]\d{2}[LR]?[A-Z]{0,3}(?:\.\d)?)\b/u', $tail, $asia);
        preg_match('/\b(L[BN]?\d(?:\.?[LR]){0,2}|[BD]\d{2}[LR]?)\b/u', $tail, $type);
        preg_match('/\(?\b([A-Z]{1,3}\d{1,3}[A-Z]{0,3}(?:-[A-Z0-9]{1,4})+|[YU]T[XZ]\w+)\b\)?/u', $tail, $motoModel);

        $isLow = preg_match('/низк/iu', $tail) === 1
            || (isset($type[1]) && str_starts_with($type[1], 'LB'))
            || (! $isMoto && ! $isTruck && ! $isVrla && isset($dims[3]) && (int) $dims[1] >= 200 && (int) $dims[3] <= 178);

        $head = $this->batteryHead($found['head']);
        $model = null;

        if ($motoPrefix && $head !== '') {
            [$head, $model] = array_pad(explode(' ', $head, 2), 2, null);
        }

        // Название без бренда («65Ah о.п. …») или с цифрой вместо бренда («3 МТ-8») производителя не даёт.
        $hasBrand = $head !== '' && preg_match('/^\d/u', $head) !== 1;

        if (! $hasBrand) {
            $spec['warnings'][] = 'в названии 1С нет бренда';
        }

        $isAsiaWord = preg_match('/азия|asia|jis/iu', $tail) === 1 && preg_match('/asia|азия/iu', $head) !== 1;
        $showAsiaWord = $isAsiaWord && ! $isMoto && ! $isTruck && ! $isVrla && ! isset($asia[1]);

        $parts = [$head, $motoPrefix ? 'moto' : null, $capacityLabel.'Ah', $showAsiaWord ? 'Asia' : null];

        if ($polarity[0] !== null) {
            $parts[] = $polarity[0];
        }

        if ($isLow) {
            $parts[] = 'низкий';
        }

        if (! $isMoto && ! $isTruck && ! $isVrla && isset($asia[1])) {
            $parts[] = $asia[1];
        }

        if ($isMoto && ($model !== null || isset($motoModel[1]))) {
            $parts[] = $model ?? $motoModel[1];
        }

        if ($isMoto && preg_match('/\biGEL\b/u', $tail) === 1 && preg_match('/\biGEL\b/u', (string) $model) !== 1) {
            $parts[] = 'iGEL';
        }

        $spec['name'] = implode(' ', array_filter($parts));
        $spec['is_battery'] = true;
        $spec['brand'] = $hasBrand ? $head : null;
        $spec['terminal'] = preg_match('/конус|болт|резьба/iu', $tail, $terminal) === 1 ? mb_strtolower($terminal[0]) : null;
        $spec['code'] = $asia[1] ?? ($type[1] ?? ($isMoto ? ($motoModel[1] ?? null) : null));
        $spec['category'] = match (true) {
            $isTruck => 'Грузовые аккумуляторы',
            $isMoto => 'Мото аккумуляторы',
            $isVrla => 'ИБП',
            default => 'Аккумуляторы',
        };

        $attributes = ['voltage' => '12V', 'capacity' => $capacityLabel.' Ah'];

        if ($polarity[1] !== null) {
            $attributes['polarity'] = $polarity[1];
        }

        if (isset($cca[1])) {
            $attributes['cca'] = $cca[1].'A';
        }

        if (isset($dims[1])) {
            $attributes['dimensions'] = "{$dims[1]}x{$dims[2]}x{$dims[3]}";
        }

        $spec['attributes'] = $attributes;

        foreach (['polarity' => 'полярность', 'cca' => 'ток', 'dimensions' => 'габариты'] as $key => $label) {
            if (! isset($attributes[$key])) {
                $spec['warnings'][] = "не нашли {$label}";
            }
        }

        return $spec;
    }

    /**
     * «ALPHALINE SD+» → «ALPHALINE SD», «DELKOR (JP)» → «DELKOR JP», «Азия» → «ASIA»;
     * «12v» из названия серии убирается (напряжение — отдельная характеристика).
     */
    private function batteryHead(string $head): string
    {
        $head = (string) preg_replace('/[()+]/u', ' ', $head);
        $head = (string) preg_replace('/\b12v\b/iu', ' ', $head);
        $head = (string) preg_replace('/\bазия\b/iu', 'ASIA', $head);

        return $this->clean($head);
    }

    public function category(string $name): ?string
    {
        foreach (self::CATEGORY_KEYWORDS as $pattern => $category) {
            if (preg_match($pattern, $name) === 1) {
                return $category;
            }
        }

        return null;
    }

    private function clean(string $name): string
    {
        return $this->fixLookalikes(trim((string) preg_replace('/\s+/u', ' ', $name)));
    }

    /**
     * В названиях 1С встречаются слова с перепутанными латинскими и русскими
     * буквами («Уплoтнитeль», «LА-1505»): поиск по ним не работает. В слове
     * остаётся алфавит, которого в нём больше; буквы-двойники другого алфавита
     * заменяются.
     */
    public function fixLookalikes(string $name): string
    {
        $toCyrillic = ['a' => 'а', 'c' => 'с', 'e' => 'е', 'o' => 'о', 'p' => 'р', 'x' => 'х', 'y' => 'у', 'k' => 'к', 'm' => 'м', 't' => 'т', 'h' => 'н', 'b' => 'в',
            'A' => 'А', 'B' => 'В', 'C' => 'С', 'E' => 'Е', 'H' => 'Н', 'K' => 'К', 'M' => 'М', 'O' => 'О', 'P' => 'Р', 'T' => 'Т', 'X' => 'Х', 'Y' => 'У'];
        $toLatin = array_flip($toCyrillic);

        return (string) preg_replace_callback('/[A-Za-zА-Яа-яЁё]+/u', function (array $match) use ($toCyrillic, $toLatin): string {
            $word = $match[0];
            $cyrillic = preg_match_all('/[А-Яа-яЁё]/u', $word);
            $latin = preg_match_all('/[A-Za-z]/', $word);

            if ($cyrillic === 0 || $latin === 0) {
                return $word;
            }

            // Поровну («cтeклa»): слово русское, если все его латинские буквы
            // имеют русских двойников; иначе — латинское, если так же с русскими.
            $toCyrillicPossible = preg_match('/[^'.preg_quote(implode('', array_keys($toCyrillic)), '/').'А-Яа-яЁё]/u', $word) === 0;
            $toLatinPossible = preg_match('/[^'.preg_quote(implode('', array_keys($toLatin)), '/').'A-Za-z]/u', $word) === 0;

            // Короткие слова («СA») — почти всегда артикулы, их оставляем латиницей.
            $short = mb_strlen($word) <= 3;

            return match (true) {
                $cyrillic > $latin => strtr($word, $toCyrillic),
                $latin > $cyrillic => strtr($word, $toLatin),
                $short && $toLatinPossible => strtr($word, $toLatin),
                $toCyrillicPossible => strtr($word, $toCyrillic),
                $toLatinPossible => strtr($word, $toLatin),
                default => $word,
            };
        }, $name);
    }
}
