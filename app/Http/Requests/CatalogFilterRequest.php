<?php

namespace App\Http\Requests;

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
     * @return list<int>
     */
    public function manufacturerIds(): array
    {
        return self::positiveInts($this->query('manufacturer'));
    }

    /**
     * id характеристики → выбранные id значений.
     *
     * @return array<int, list<int>>
     */
    public function attributeValueIds(): array
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

    public function priceFrom(): ?float
    {
        return self::price($this->query('price_from'));
    }

    public function priceTo(): ?float
    {
        return self::price($this->query('price_to'));
    }

    public function onlyAvailable(): bool
    {
        return $this->boolean('available');
    }

    public function isActive(): bool
    {
        return $this->manufacturerIds() !== []
            || $this->attributeValueIds() !== []
            || $this->priceFrom() !== null
            || $this->priceTo() !== null
            || $this->onlyAvailable();
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
