<?php

namespace App\Services\Catalog;

use App\Models\BatteryFitment;
use App\Models\Product;
use App\Models\TradeInType;
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
    // v2: в сводке поля раздельно (тип, размеры, ёмкость, полярность), а не готовая строка.
    private const CACHE_KEY = 'assortment-gaps-v2';

    /**
     * @return array{cars: int, without_batteries: int, sizes: list<array{type: ?string, dimensions: ?string, capacity: ?string, polarity: string, cars: int}>}
     */
    public function summary(): array
    {
        return Cache::get(self::CACHE_KEY) ?? $this->refresh();
    }

    /**
     * @return array{cars: int, without_batteries: int, sizes: list<array{type: ?string, dimensions: ?string, capacity: ?string, polarity: string, cars: int}>}
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

        $sizeTypes = $this->sizeTypes();

        $summary = [
            'cars' => $fitments->count(),
            'without_batteries' => $unserved->sum(fn (Collection $group): int => $group->count()),
            'sizes' => $unserved
                ->flatMap(fn (Collection $group): array => array_fill(0, $group->count(), $this->need($group->first(), $sizeTypes)))
                ->groupBy(fn (array $need): string => json_encode($need, JSON_UNESCAPED_UNICODE))
                ->map(fn (Collection $needs): array => [...$needs->first(), 'cars' => $needs->count()])
                ->sortByDesc('cars')
                ->take(15)
                ->values()
                ->all(),
        ];

        Cache::put(self::CACHE_KEY, $summary, now()->addHours(26));

        return $summary;
    }

    /**
     * Чего не хватает машине: основной (первый) размер с типом корпуса из таблицы типов
     * (если подходит), иначе ёмкость. Поля раздельные; подпись собирает label().
     *
     * @param  Collection<int, array{code: string, dims: array{int, int, int}}>  $sizeTypes
     * @return array{type: ?string, dimensions: ?string, capacity: ?string, polarity: string}
     */
    private function need(BatteryFitment $fitment, Collection $sizeTypes): array
    {
        $dims = $fitment->dimensions()[0] ?? null;
        $polarity = BatteryFitment::polarityList([$fitment->polarity])[0] ?? '—';

        if ($dims === null) {
            return ['type' => null, 'dimensions' => null, 'capacity' => implode(', ', $fitment->capacities()), 'polarity' => $polarity];
        }

        // Тип — подпись, а не подбор: допуски шире, чтобы «236×128×220» назвался B24.
        $type = $sizeTypes->first(fn (array $size): bool => abs($size['dims'][0] - $dims[0]) <= 3
            && abs($size['dims'][1] - $dims[1]) <= 5
            && abs($size['dims'][2] - $dims[2]) <= 10);

        return ['type' => $type['code'] ?? null, 'dimensions' => implode(' x ', $dims), 'capacity' => null, 'polarity' => $polarity];
    }

    /**
     * Подпись для вывода: «B24, 236 x 128 x 220 мм, Обратная» или «Ёмкость 190 Ач, Обратная».
     *
     * @param  array{type: ?string, dimensions: ?string, capacity: ?string, polarity: string}  $gap
     */
    public static function label(array $gap): string
    {
        $size = $gap['dimensions'] === null
            ? "Ёмкость {$gap['capacity']} Ач"
            : trim(($gap['type'] !== null ? $gap['type'].', ' : '').$gap['dimensions'].' мм');

        return "{$size}, {$gap['polarity']}";
    }

    /**
     * Типоразмеры из таблицы типов: код и номинальные размеры (середина диапазона).
     *
     * @return Collection<int, array{code: string, dims: array{int, int, int}}>
     */
    private function sizeTypes(): Collection
    {
        return TradeInType::query()->active()->ordered()->get()
            ->filter(fn (TradeInType $type): bool => $type->hasDimensionRange())
            ->map(fn (TradeInType $type): array => ['code' => $type->code, 'dims' => [
                intdiv($type->length_min + $type->length_max, 2),
                intdiv($type->width_min + $type->width_max, 2),
                intdiv($type->height_min + $type->height_max, 2),
            ]])
            ->values();
    }
}
