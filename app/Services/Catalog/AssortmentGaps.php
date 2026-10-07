<?php

namespace App\Services\Catalog;

use App\Models\AttributeValue;
use App\Models\BatteryFitment;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Спрос, которого нет в ассортименте: машины из базы подбора (кроме
 * спецтехники), которым подбор не находит ни одного аккумулятора, и какие
 * типоразмеры им нужны. Подсказка, что заказать у поставщика.
 *
 * Расчёт тяжёлый (десятки тысяч машин) — делается ночью командой
 * assortment:gaps и лежит в кеше.
 */
class AssortmentGaps
{
    private const CACHE_KEY = 'assortment-gaps';

    /**
     * @return array{cars: int, without_batteries: int, sizes: list<array{size: string, polarity: string, cars: int}>}
     */
    public function summary(): array
    {
        return Cache::get(self::CACHE_KEY) ?? $this->refresh();
    }

    /**
     * @return array{cars: int, without_batteries: int, sizes: list<array{size: string, polarity: string, cars: int}>}
     */
    public function refresh(): array
    {
        $fitments = BatteryFitment::query()
            ->whereNotIn('brand', config('shop.car_landings.excluded_brands'))
            ->get()
            ->filter(fn (BatteryFitment $fitment): bool => $fitment->hasSelectionData());

        // Одинаковые по параметрам машины проверяем один раз.
        $groups = $fitments->groupBy(fn (BatteryFitment $fitment): string => $fitment->capacity.'|'.$fitment->polarity.'|'.$fitment->dims.'|'.$fitment->terminals);
        $unserved = $groups->filter(fn (Collection $group): bool => ! Product::query()->where('status', true)->fitsBattery($group->first())->exists());

        $sizeNames = $this->sizeNames();

        $summary = [
            'cars' => $fitments->count(),
            'without_batteries' => $unserved->sum(fn (Collection $group): int => $group->count()),
            'sizes' => $unserved
                ->flatMap(fn (Collection $group): array => array_fill(0, $group->count(), $this->need($group->first(), $sizeNames)))
                ->countBy(fn (array $need): string => $need['size'].'|'.$need['polarity'])
                ->sortDesc()
                ->take(15)
                ->map(function (int $cars, string $key): array {
                    [$size, $polarity] = explode('|', $key);

                    return ['size' => $size, 'polarity' => $polarity, 'cars' => $cars];
                })
                ->values()
                ->all(),
        ];

        Cache::put(self::CACHE_KEY, $summary, now()->addHours(26));

        return $summary;
    }

    /**
     * Основной (первый) размер машины названием типоразмера из каталога
     * («Азия D31 (306 x 173 x 225 мм)»), если такой есть, иначе «Д×Ш×В».
     *
     * @param  Collection<int, array{name: string, dims: array{int, int, int}}>  $sizeNames
     * @return array{size: string, polarity: string}
     */
    private function need(BatteryFitment $fitment, Collection $sizeNames): array
    {
        $dims = $fitment->dimensions()[0] ?? null;
        $polarity = BatteryFitment::polarityList([$fitment->polarity])[0] ?? '—';

        if ($dims === null) {
            return ['size' => 'Ёмкость '.implode(', ', $fitment->capacities()).' Ач', 'polarity' => $polarity];
        }

        // Подпись, а не подбор: допуски шире, чтобы «236×128×220» назвался «Азия B24».
        $named = $sizeNames->first(fn (array $size): bool => abs($size['dims'][0] - $dims[0]) <= 3
            && abs($size['dims'][1] - $dims[1]) <= 5
            && abs($size['dims'][2] - $dims[2]) <= 10);

        return ['size' => $named['name'] ?? implode('×', $dims).' мм', 'polarity' => $polarity];
    }

    /**
     * @return Collection<int, array{name: string, dims: array{int, int, int}}>
     */
    private function sizeNames(): Collection
    {
        return AttributeValue::query()
            ->where('attribute_id', config('shop.battery_fitment.attributes.dimensions'))
            ->pluck('value')
            ->map(fn (string $value): ?array => preg_match('/(\d+)\s*[xх×]\s*(\d+)\s*[xх×]\s*(\d+)/u', $value, $matches)
                ? ['name' => $value, 'dims' => [(int) $matches[1], (int) $matches[2], (int) $matches[3]]]
                : null)
            ->filter()
            ->values();
    }
}
