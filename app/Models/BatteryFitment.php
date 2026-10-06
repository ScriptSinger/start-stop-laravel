<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class BatteryFitment extends Model
{
    protected $fillable = [
        'brand',
        'model',
        'generation',
        'capacity',
        'polarity',
        'dims',
        'image',
    ];

    /**
     * Название машины без повторов: в базе модель иногда уже содержит марку
     * («Kia» + «Kia Rio»), а поколение — марку и модель («Kia Rio IV 2017 - 2020»).
     */
    public function displayName(): string
    {
        $brand = trim((string) $this->brand);
        $model = trim((string) $this->model);
        $generation = trim((string) $this->generation);

        $name = str_starts_with(mb_strtolower($model), mb_strtolower($brand)) ? $model : trim($brand.' '.$model);

        if ($generation === '') {
            return $name;
        }

        return str_starts_with(mb_strtolower($generation), mb_strtolower($brand))
            ? $generation
            : $name.' '.$generation;
    }

    /**
     * Есть ли у записи данные для подбора. У 1803 записей базы их нет вовсе
     * (ни ёмкости, ни габаритов, ни полярности).
     */
    public function hasSelectionData(): bool
    {
        return $this->capacities() !== [] || $this->dimensions() !== [] || $this->polarityValues() !== null;
    }

    /**
     * "60 Ач, 55 Ач, 62 Ач" → [60, 55, 62].
     *
     * @return list<int>
     */
    public function capacities(): array
    {
        preg_match_all('/\d+/', (string) $this->capacity, $matches);

        return array_values(array_unique(array_map('intval', $matches[0])));
    }

    /**
     * "242x175x190 , 230x173x225" → [[242, 175, 190], [230, 173, 225]].
     *
     * @return list<array{int, int, int}>
     */
    public function dimensions(): array
    {
        return collect(explode(',', (string) $this->dims))
            ->map(fn (string $dims): ?array => self::parseDimensions($dims))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Подходящие значения характеристики «Полярность». Универсальная АКБ
     * подходит к любой. Если полярность у машины не указана — не фильтруем
     * (старый код в этом случае ошибочно считал её прямой).
     *
     * @return list<string>|null
     */
    public function polarityValues(): ?array
    {
        $polarity = mb_strtolower((string) $this->polarity);

        return match (true) {
            str_contains($polarity, 'обрат') => ['Обратная', 'Универсальная'],
            str_contains($polarity, 'прям') => ['Прямая', 'Универсальная'],
            default => null,
        };
    }

    /**
     * Для каждого условия подбора — id подходящих значений характеристик.
     * null — условия нет (данных по машине нет), пустой список — данные
     * есть, но ни одно значение не подходит: тогда подбор ничего не найдёт,
     * а не расширится молча до всех аккумуляторов (как было в старом коде).
     *
     * @return array<string, list<int>|null>
     */
    public function matchingAttributeValueIds(): array
    {
        $attributeIds = config('shop.battery_fitment.attributes');
        $lengthTolerance = (int) config('shop.battery_fitment.length_tolerance_mm');
        $sizeTolerance = (int) config('shop.battery_fitment.size_tolerance_mm');

        /** @var Collection<int, Collection<int, AttributeValue>> $values */
        $values = AttributeValue::query()
            ->whereIn('attribute_id', $attributeIds)
            ->get()
            ->groupBy('attribute_id');

        $valuesOf = fn (string $criterion): Collection => $values->get($attributeIds[$criterion], collect());

        $polarities = $this->polarityValues();
        $capacities = $this->capacities();
        $dimensions = $this->dimensions();

        return [
            'polarity' => $polarities === null ? null : $valuesOf('polarity')
                ->filter(fn (AttributeValue $value): bool => in_array($value->value, $polarities, true))
                ->modelKeys(),
            'capacity_range' => $capacities === [] ? null : $valuesOf('capacity_range')
                ->filter(function (AttributeValue $value) use ($capacities): bool {
                    if (! preg_match('/(\d+)\s*-\s*(\d+)/', $value->value, $range)) {
                        return false;
                    }

                    return collect($capacities)->contains(fn (int $capacity): bool => $capacity >= (int) $range[1] && $capacity <= (int) $range[2]);
                })
                ->modelKeys(),
            // АКБ подходит, если совпадает по длине с одним из размеров машины
            // (из «Д×Ш×В», а не любое число в названии: «Азия D26 (260 x 173 x 225 мм)»
            // в старом коде совпадало и с 26, и с 173) и не шире и не выше его.
            // Габариты без размеров («Груз B (180 - 190 Ah)» у грузовых АКБ)
            // не ограничивают — такой АКБ подбирается по ёмкости и полярности.
            'dimensions' => $dimensions === [] ? null : $valuesOf('dimensions')
                ->filter(function (AttributeValue $value) use ($dimensions, $lengthTolerance, $sizeTolerance): bool {
                    $battery = self::parseDimensions($value->value);

                    return $battery === null || collect($dimensions)->contains(
                        fn (array $car): bool => abs($car[0] - $battery[0]) <= $lengthTolerance
                            && $battery[1] <= $car[1] + $sizeTolerance
                            && $battery[2] <= $car[2] + $sizeTolerance,
                    );
                })
                ->modelKeys(),
        ];
    }

    /**
     * @return array{int, int, int}|null
     */
    private static function parseDimensions(string $text): ?array
    {
        if (! preg_match('/(\d+)\s*[xх×]\s*(\d+)\s*[xх×]\s*(\d+)/u', $text, $matches)) {
            return null;
        }

        return [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
    }
}
