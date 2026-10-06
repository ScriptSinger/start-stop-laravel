<?php

namespace App\Services\CarLanding;

use App\Models\BatteryFitment;

/**
 * Параметры аккумулятора по записям подбора ($this->fitments): общие для
 * страницы модели и страницы поколения.
 */
trait DescribesFitments
{
    /**
     * Ёмкости по возрастанию.
     *
     * @return list<int>
     */
    public function capacities(): array
    {
        return $this->fitments->flatMap(fn (BatteryFitment $fitment): array => $fitment->capacities())->unique()->sort()->values()->all();
    }

    /**
     * Полярности машины: «Обратная», «Прямая». «Универсальная» в данных
     * означает, что подойдёт и такой АКБ, — покупателю её не показываем.
     *
     * @return list<string>
     */
    public function polarities(): array
    {
        return BatteryFitment::polarityList($this->fitments->pluck('polarity')->all());
    }

    /**
     * Габариты «Д×Ш×В» без повторов.
     *
     * @return list<string>
     */
    public function dimensions(): array
    {
        return $this->fitments
            ->flatMap(fn (BatteryFitment $fitment): array => $fitment->dimensions())
            ->map(fn (array $dims): string => implode('×', $dims))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Длина корпуса: «241–246» или «242».
     */
    public function lengthRange(): ?string
    {
        $lengths = $this->fitments
            ->flatMap(fn (BatteryFitment $fitment): array => array_column($fitment->dimensions(), 0))
            ->unique();

        if ($lengths->isEmpty()) {
            return null;
        }

        return $lengths->min() === $lengths->max() ? (string) $lengths->min() : $lengths->min().'–'.$lengths->max();
    }

    /**
     * Наибольшая допустимая высота корпуса: выше этого аккумулятор не встанет
     * ни в один из вариантов отсека (175 — низкий европейский, 190 — обычный).
     */
    public function maxHeight(): ?int
    {
        $heights = $this->fitments->flatMap(fn (BatteryFitment $fitment): array => array_column($fitment->dimensions(), 2));

        return $heights->isEmpty() ? null : (int) $heights->max();
    }
}
