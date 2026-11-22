<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\TradeInType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Подбор типа корпуса по «Габаритам» товара: подсказка при массовом назначении
 * (trade-in:assign-types) и для новых товаров из 1С. Скидку считает не он: у товара
 * тип записан отдельным полем (trade_in_type_id), и скидка берётся из него.
 *
 * Тип подбирается так:
 *  1. в названии значения «Габаритов» есть код типа («Евро L3 (…)» → L3);
 *  2. иначе размеры из значения («277x175x190») попадают в диапазон ровно одного типа.
 * Не нашёлся тип или подходят два — подбора нет (тип ставит менеджер вручную).
 */
class TradeInTypeMatcher
{
    /** @var Collection<int, TradeInType>|null */
    private ?Collection $types = null;

    /**
     * Активные типы по порядку; загружаются один раз за запрос.
     *
     * @return Collection<int, TradeInType>
     */
    public function types(): Collection
    {
        return $this->types ??= TradeInType::query()->active()->ordered()->get();
    }

    /**
     * Подставить свой набор типов (пробный прогон: типы, которых ещё нет в базе).
     *
     * @param  Collection<int, TradeInType>  $types
     */
    public function useTypes(Collection $types): void
    {
        $this->types = $types->values();
    }

    /**
     * Сбросить загруженные типы (после изменения таблицы типов).
     */
    public function flush(): void
    {
        $this->types = null;
    }

    /**
     * Нужна загруженная связь attributeValues.
     */
    public function typeForProduct(Product $product): ?TradeInType
    {
        $dimensionsAttribute = (int) config('shop.battery_fitment.attributes.dimensions');

        return $this->typeFor($product->attributeValues
            ->filter(fn ($value): bool => $value->attribute_id === $dimensionsAttribute)
            ->pluck('value')
            ->all());
    }

    /**
     * @param  list<string>  $dimensionValues  названия значений «Габаритов» товара
     */
    public function typeFor(array $dimensionValues): ?TradeInType
    {
        foreach ($dimensionValues as $name) {
            $type = $this->types()->first(fn (TradeInType $candidate): bool => $this->nameHasCode($name, $candidate->code));

            if ($type !== null) {
                return $type;
            }
        }

        foreach ($dimensionValues as $name) {
            if (($size = self::parseDimensions($name)) !== null) {
                $matching = $this->types()->filter(fn (TradeInType $type): bool => $type->containsDimensions(...$size));

                if ($matching->isNotEmpty()) {
                    return $matching->count() === 1 ? $matching->first() : null;
                }
            }
        }

        return null;
    }

    /**
     * Для всех товаров с габаритами: тип и первое значение «Габаритов» одним проходом
     * (выгрузка, отчёты) — без запросов на каждый товар.
     *
     * @return array<int, array{type: ?TradeInType, dimensions: string}>
     */
    public function resolveAll(): array
    {
        $rows = DB::table('attribute_value_product')
            ->join('attribute_values', 'attribute_values.id', '=', 'attribute_value_product.attribute_value_id')
            ->where('attribute_values.attribute_id', (int) config('shop.battery_fitment.attributes.dimensions'))
            ->get(['attribute_value_product.product_id', 'attribute_values.value'])
            ->groupBy('product_id');

        return $rows->map(fn (Collection $values): array => [
            'type' => $this->typeFor($values->pluck('value')->all()),
            'dimensions' => $values->first()->value,
        ])->all();
    }

    /**
     * Длина, ширина, высота из текста («277x175x190», «… (278 x 175 x 190 мм)»).
     *
     * @return array{int, int, int}|null
     */
    public static function parseDimensions(string $text): ?array
    {
        if (preg_match('/(\d{2,3})\s*[xх×]\s*(\d{2,3})\s*[xх×]\s*(\d{2,3})/iu', $text, $match) !== 1) {
            return null;
        }

        return [(int) $match[1], (int) $match[2], (int) $match[3]];
    }

    /**
     * Единый формат значения «Габаритов» — только размеры, без единицы: «242 x 175 x 190»
     * (миллиметры подразумеваются). Текст без трёх размеров возвращается как есть.
     */
    public static function formatDimensions(string $text): string
    {
        $size = self::parseDimensions($text);

        return $size === null ? $text : implode(' x ', $size);
    }

    private function nameHasCode(string $name, string $code): bool
    {
        $pattern = str_replace('\ ', '\s+', preg_quote($code, '/'));

        return preg_match('/(?<![\p{L}\p{N}])'.$pattern.'(?![\p{L}\p{N}])/iu', $name) === 1;
    }
}
