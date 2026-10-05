<?php

namespace App\Http\Requests;

use App\Services\Catalog\CatalogFilter;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Параметры фильтра каталога из адресной строки:
 * ?manufacturer[]=5&attr[13][]=42&attr[20][]=7&price_from=5000&price_to=9000&available=1
 *
 * Фильтр — обычная GET-ссылка, которую могут исказить вручную или боты,
 * поэтому мусор не валидируется с ошибкой, а просто отбрасывается.
 */
class CatalogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Разбор адресной строки в объект фильтра; дальше с запросом никто не работает.
     */
    public function toFilter(): CatalogFilter
    {
        return new CatalogFilter(
            manufacturerIds: self::positiveInts($this->query('manufacturer')),
            attributeValueIds: $this->attributeValueIds(),
            priceFrom: self::price($this->query('price_from')),
            priceTo: self::price($this->query('price_to')),
            onlyAvailable: $this->boolean('available'),
        );
    }

    /**
     * @return array<int, list<int>>
     */
    private function attributeValueIds(): array
    {
        $attributes = $this->query('attr');

        if (! is_array($attributes)) {
            return [];
        }

        $selected = [];

        foreach ($attributes as $attributeId => $valueIds) {
            $valueIds = self::positiveInts($valueIds);

            if (is_numeric($attributeId) && (int) $attributeId > 0 && $valueIds !== []) {
                $selected[(int) $attributeId] = $valueIds;
            }
        }

        return $selected;
    }

    /**
     * @return list<int>
     */
    private static function positiveInts(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', array_filter(
            $values,
            fn (mixed $value): bool => is_numeric($value) && (int) $value > 0,
        ))));
    }

    private static function price(mixed $value): ?float
    {
        if (! is_numeric($value) || (float) $value < 0) {
            return null;
        }

        return (float) $value;
    }
}
