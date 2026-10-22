<?php

namespace App\Services\Catalog;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Добавляет товарам недостающие характеристики, выводя их из названия (своего
 * и из прайса 1С). Только добавляет: если у товара по характеристике уже есть
 * значение, она не трогается. Характеристики берутся те, что относятся к
 * разделам товара (attribute_category).
 *
 * Аккумуляторы: ёмкость (и диапазон), напряжение, полярность, ток (и диапазон),
 * габариты, технология, токовыводы, производство (по основной стране
 * производителя). Масла: вязкость, объём, состав, API, ACEA. Значения из
 * закрытых справочников (технология, токовыводы, состав, API, ACEA, страна)
 * берутся только существующие; ёмкость, ток, габариты, вязкость и объём
 * могут создать новое значение.
 */
class ProductAttributeFiller
{
    /** Характеристики, значения которых при отсутствии в справочнике создаются. */
    private const OPEN = ['Емкость', 'Ток холодной прокрутки', 'Габариты', 'Вязкость', 'Объем'];

    private const NAMES = [
        'voltage' => 'Напряжение', 'polarity' => 'Полярность', 'country' => 'Производство', 'capacity' => 'Емкость',
        'dimensions' => 'Габариты', 'cca' => 'Ток холодной прокрутки', 'technology' => 'Технология', 'terminals' => 'Токовыводы',
        'capacity_range' => 'Ёмкость (Ah)', 'cca_range' => 'Ток холодного пуска (А)', 'viscosity' => 'Вязкость', 'acea' => 'ACEA',
        'api' => 'API', 'composition' => 'Состав', 'volume' => 'Объем',
    ];

    /**
     * Что получит каждый товар.
     *
     * @param  Collection<int, Product>  $products
     * @param  array<string, string>  $onecNames  название из прайса 1С по коду 1С
     * @return Collection<int, array{product: Product, existing: array<string, list<int>>, create: array<string, list<string>>, label: array<string, list<string>>}>
     */
    public function plan(Collection $products, array $onecNames = []): Collection
    {
        $attributes = Attribute::query()->pluck('id', 'name');
        $values = AttributeValue::query()->get()->groupBy('attribute_id');
        $allowed = DB::table('attribute_category')->get()->groupBy('category_id')->map(fn (Collection $rows): array => $rows->pluck('attribute_id')->all());
        $have = DB::table('attribute_value_product as ap')
            ->join('attribute_values as v', 'v.id', '=', 'ap.attribute_value_id')
            ->whereIn('ap.product_id', $products->modelKeys())
            ->get(['ap.product_id', 'v.attribute_id'])
            ->groupBy('product_id')
            ->map(fn (Collection $rows): array => $rows->pluck('attribute_id')->unique()->all());
        $countries = $this->manufacturerCountries($attributes->get(self::NAMES['country']));
        $carCategories = (array) config('shop.battery_fitment.category_ids');

        return $products->load('categories:id')->map(function (Product $product) use ($attributes, $values, $allowed, $have, $countries, $carCategories, $onecNames): array {
            $categoryIds = $product->categories->modelKeys();
            $allowedIds = collect($categoryIds)->flatMap(fn (int $id): array => $allowed->get($id, []))->unique();
            $haveIds = collect($have->get($product->id, []));
            $texts = array_values(array_filter([$product->name, $onecNames[$product->external_id] ?? null]));
            $isCar = array_intersect($categoryIds, $carCategories) !== [];

            $derived = $this->derive($texts, $isCar, $countries[$product->manufacturer_id] ?? null);

            $existing = [];
            $create = [];
            $label = [];

            foreach ($derived as $key => $wanted) {
                $attributeId = $attributes->get(self::NAMES[$key]);

                if ($attributeId === null || ! $allowedIds->contains($attributeId) || $haveIds->contains($attributeId)) {
                    continue;
                }

                foreach ($wanted as $value) {
                    $matches = $this->matches($values->get($attributeId, collect()), $value, $key);

                    foreach ($matches as $found) {
                        $existing[self::NAMES[$key]][] = $found->id;
                        $label[self::NAMES[$key]][] = $found->value;
                    }

                    if ($matches->isEmpty() && in_array(self::NAMES[$key], self::OPEN, true)) {
                        $create[self::NAMES[$key]][] = $value;
                        $label[self::NAMES[$key]][] = $value.' (новое)';
                    }
                }
            }

            return ['product' => $product, 'existing' => $existing, 'create' => $create, 'label' => $label];
        })->filter(fn (array $item): bool => $item['existing'] !== [] || $item['create'] !== [])->values();
    }

    /**
     * Записать характеристики по плану; недостающие значения создаются.
     *
     * @param  Collection<int, array{product: Product, existing: array<string, list<int>>, create: array<string, list<string>>, label: array<string, list<string>>}>  $plan
     */
    public function apply(Collection $plan): int
    {
        $attributes = Attribute::query()->pluck('id', 'name');

        DB::transaction(function () use ($plan, $attributes): void {
            foreach ($plan as $item) {
                $ids = collect($item['existing'])->flatten();

                foreach ($item['create'] as $attributeName => $newValues) {
                    foreach ($newValues as $value) {
                        $ids->push(AttributeValue::query()->firstOrCreate(
                            ['attribute_id' => $attributes[$attributeName], 'value' => $value],
                            ['sort_order' => 0],
                        )->id);
                    }
                }

                DB::table('attribute_value_product')->insertOrIgnore($ids->unique()->map(fn (int $id): array => [
                    'attribute_value_id' => $id,
                    'product_id' => $item['product']->id,
                ])->all());
            }
        });

        return $plan->count();
    }

    /**
     * @param  list<string>  $texts  названия: сначала с сайта, затем из 1С
     * @return array<string, list<string>>
     */
    private function derive(array $texts, bool $isCar, ?string $country): array
    {
        $first = function (string $pattern) use ($texts): ?array {
            foreach ($texts as $text) {
                if (preg_match($pattern, $text, $match) === 1) {
                    return $match;
                }
            }

            return null;
        };
        $all = implode(' | ', $texts);
        $derived = [];

        if ($capacity = $first('/(\d+(?:[.,]\d+)?)\s*(?:Ah|Ач|А\*ч)\b/iu')) {
            $number = (float) str_replace(',', '.', $capacity[1]);
            $derived['capacity'] = [str_replace('.', ',', rtrim(rtrim(number_format($number, 1, '.', ''), '0'), '.')).' Ah'];
            $derived['capacity_range'] = ['range:'.$number];
        }

        if ($voltage = $first('/\b(12|6)\s*v\b/iu')) {
            $derived['voltage'] = [$voltage[1].'V'];
        } elseif ($isCar && isset($derived['capacity'])) {
            $derived['voltage'] = ['12V'];
        }

        $derived['polarity'] = match (true) {
            preg_match('/\bо\s*\.?\s*п\b/iu', $all) === 1 => ['Обратная'],
            preg_match('/\bп\s*\.?\s*п\b/iu', $all) === 1 => ['Прямая'],
            preg_match('/\bуни\b/iu', $all) === 1 => ['Универсальная'],
            default => [],
        };

        if ($cca = $first('/(\d{3,4})\s*[AА]\b/u')) {
            $derived['cca'] = [$cca[1].'A'];
            $derived['cca_range'] = ['range:'.$cca[1]];
        }

        if ($dims = $first('/(\d{2,3})\s*[xх×]\s*(\d{2,3})\s*[xх×]\s*(\d{2,3})/u')) {
            $derived['dimensions'] = ["{$dims[1]}x{$dims[2]}x{$dims[3]}"];
        }

        $derived['technology'] = match (true) {
            preg_match('/\bAGM\b/iu', $all) === 1 => ['AGM'],
            preg_match('/\bEFB\b|\+EFB/iu', $all) === 1 => ['EFB'],
            preg_match('/\bi?GEL\b/iu', $all) === 1 => ['Gel'],
            default => [],
        };

        $derived['terminals'] = match (true) {
            preg_match('/боков/iu', $all) === 1 => ['Боковые'],
            preg_match('/резьб/iu', $all) === 1 => ['Резьбовые'],
            preg_match('/конус\s+тонк/iu', $all) === 1 => ['конус тонкие'],
            preg_match('/\bболт\b/iu', $all) === 1 => ['Болт'],
            preg_match('/\bконус\b/iu', $all) === 1 => ['Стандарт конус'],
            default => [],
        };

        if ($country !== null) {
            $derived['country'] = [$country];
        }

        if ($viscosity = $first('/\b(\d{1,2})\s*w[\s\-]?(\d{2})\b/iu')) {
            $derived['viscosity'] = [$viscosity[1].'W'.$viscosity[2]];
        }

        if ($volume = $first('/(\d+(?:[.,]\d+)?)\s*л\b/iu')) {
            $derived['volume'] = [str_replace(',', '.', $volume[1]).'л.'];
        }

        $derived['composition'] = match (true) {
            preg_match('/п\/синт|полусинт/iu', $all) === 1 => ['Полусинтетические'],
            preg_match('/синт/iu', $all) === 1 => ['Синтетические'],
            default => [],
        };

        $tokens = preg_split('/[^A-Za-z0-9\-]+/', $all, -1, PREG_SPLIT_NO_EMPTY);
        $derived['api'] = array_values(array_unique(array_filter($tokens, fn (string $token): bool => preg_match('/^(?:CD|CF|CH-4|CI-4|SG|SJ|SL|SM|SN|SP|GF-\d+A?|GL-\d)$/i', $token) === 1)));
        $derived['acea'] = array_values(array_unique(array_filter($tokens, fn (string $token): bool => preg_match('/^[ABCE]\d$/i', $token) === 1)));

        return array_filter($derived, fn (array $values): bool => $values !== []);
    }

    /**
     * Существующие значения справочника: «600А» и «600A», «242x175x190» и
     * «Евро L2 (242 x 175 x 190 мм)» считаются одним значением; «range:N» —
     * все диапазоны «55 - 65 Ah», в которые попадает N (они пересекаются).
     *
     * @param  Collection<int, AttributeValue>  $values
     * @return Collection<int, AttributeValue>
     */
    private function matches(Collection $values, string $value, string $key): Collection
    {
        if (str_starts_with($value, 'range:')) {
            $number = (float) substr($value, 6);

            return $values->filter(fn (AttributeValue $candidate): bool => preg_match('/(\d+)\s*-\s*(\d+)/', $candidate->value, $bounds) === 1
                && $number >= (int) $bounds[1] && $number <= (int) $bounds[2])->values();
        }

        $wanted = $this->key($value, $key);
        $found = $values->first(fn (AttributeValue $candidate): bool => $this->key($candidate->value, $key) === $wanted);

        return $found === null ? collect() : collect([$found]);
    }

    private function key(string $value, string $kind): string
    {
        if ($kind === 'dimensions' && preg_match('/(\d{2,3})\D+(\d{2,3})\D+(\d{2,3})/u', $value, $dims) === 1) {
            $numbers = [$dims[1], $dims[2], $dims[3]];
            sort($numbers);

            return implode('x', $numbers);
        }

        return mb_strtolower((string) preg_replace('/\s+/u', '', strtr($value, ['А' => 'A', 'а' => 'a', 'х' => 'x', 'Х' => 'X', 'В' => 'B', 'С' => 'C', 'Е' => 'E', 'М' => 'M'])));
    }

    /**
     * Основная страна производителя: значение «Производство», общее у не менее
     * 85% его товаров (при трёх и более товарах с этой характеристикой).
     *
     * @return array<int, string>
     */
    private function manufacturerCountries(?int $attributeId): array
    {
        if ($attributeId === null) {
            return [];
        }

        return DB::table('attribute_value_product as ap')
            ->join('attribute_values as v', 'v.id', '=', 'ap.attribute_value_id')
            ->join('products as p', 'p.id', '=', 'ap.product_id')
            ->where('v.attribute_id', $attributeId)
            ->whereNull('p.deleted_at')
            ->whereNotNull('p.manufacturer_id')
            ->get(['p.manufacturer_id', 'v.value'])
            ->groupBy('manufacturer_id')
            ->map(fn (Collection $rows): array => $rows->countBy('value')->sortDesc()->all())
            ->filter(fn (array $counts): bool => array_sum($counts) >= 3 && reset($counts) / array_sum($counts) >= 0.85)
            ->map(fn (array $counts): string => (string) array_key_first($counts))
            ->all();
    }
}
