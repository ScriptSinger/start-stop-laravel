<?php

namespace App\Services\Catalog;

use App\Models\AttributeValue;
use Illuminate\Support\Facades\DB;

/**
 * Массовая правка характеристик у отмеченных в админке товаров
 * (как Batch Editor старой админки OpenCart).
 */
class BulkAttributeAssigner
{
    /**
     * Присвоить значение. replace — сначала убрать у товаров прежние значения
     * этой характеристики (для ёмкости, полярности — одно значение на товар).
     * Точная ёмкость («60 Ah») тянет за собой подходящие диапазоны
     * («55 - 65 Ah»): подбор по машине смотрит на них.
     *
     * @param  list<int>  $productIds
     * @return list<string> что присвоено: «60 Ah», «55 - 65 Ah»
     */
    public function assign(array $productIds, AttributeValue $value, bool $replace): array
    {
        $values = collect([$value])->merge($this->capacityRanges($value));

        DB::transaction(function () use ($productIds, $values, $replace): void {
            foreach ($values->groupBy('attribute_id') as $attributeId => $attributeValues) {
                if ($replace) {
                    $this->detach($productIds, (int) $attributeId);
                }

                DB::table('attribute_value_product')->insertOrIgnore(
                    $attributeValues->flatMap(fn (AttributeValue $value): array => array_map(
                        fn (int $productId): array => ['attribute_value_id' => $value->id, 'product_id' => $productId],
                        $productIds,
                    ))->all(),
                );
            }
        });

        return $values->pluck('value')->all();
    }

    /**
     * Убрать у товаров все значения характеристики.
     *
     * @param  list<int>  $productIds
     */
    public function detach(array $productIds, int $attributeId): int
    {
        return DB::table('attribute_value_product')
            ->whereIn('product_id', $productIds)
            ->whereIn('attribute_value_id', AttributeValue::query()->where('attribute_id', $attributeId)->select('id'))
            ->delete();
    }

    /**
     * Диапазоны ёмкости, в которые попадает точная ёмкость (диапазоны в
     * данных пересекаются: 66–77 и 68–85 — берём все подходящие).
     *
     * @return list<AttributeValue>
     */
    private function capacityRanges(AttributeValue $value): array
    {
        if ($value->attribute_id !== (int) config('shop.battery_fitment.capacity_exact_attribute')
            || ! preg_match('/\d+/', $value->value, $capacity)) {
            return [];
        }

        return AttributeValue::query()
            ->where('attribute_id', config('shop.battery_fitment.attributes.capacity_range'))
            ->get()
            ->filter(fn (AttributeValue $range): bool => preg_match('/(\d+)\s*-\s*(\d+)/', $range->value, $bounds) === 1
                && (int) $capacity[0] >= (int) $bounds[1]
                && (int) $capacity[0] <= (int) $bounds[2])
            ->values()
            ->all();
    }
}
