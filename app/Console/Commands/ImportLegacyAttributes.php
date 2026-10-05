<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Facades\DB;

#[Signature('import:legacy-attributes')]
#[Description('Импорт характеристик товаров (справочник OCFilter + значения у товаров) из старого проекта')]
class ImportLegacyAttributes extends LegacyImportCommand
{
    /**
     * Источник — таблицы OCFilter, а не oc_product_attribute: в старом проекте
     * это одни и те же данные, но в OCFilter значения уже сведены в справочник
     * ("100 - 110 Ah" один раз, а не текстом у каждого товара) — ровно то, что
     * нужно для фильтра и подбора АКБ. Товары должны быть импортированы раньше.
     */
    protected function import(): int
    {
        $this->importAttributes();
        $this->importCategoryLinks();
        $valueIdMap = $this->importValues();
        $this->importProductLinks($valueIdMap);

        return self::SUCCESS;
    }

    private function importAttributes(): void
    {
        $rows = DB::connection('legacy')
            ->table('oc_ocfilter_filter as f')
            ->join('oc_ocfilter_filter_description as fd', function ($join): void {
                $join->on('fd.filter_id', '=', 'f.filter_id')->on('fd.source', '=', 'f.source');
            })
            ->where('fd.language_id', 1)
            ->select('f.filter_id', 'f.status', 'f.sort_order', 'fd.name')
            ->get();

        foreach ($rows as $row) {
            DB::table('attributes')->updateOrInsert(
                ['id' => $row->filter_id],
                [
                    'name' => $row->name,
                    'sort_order' => $row->sort_order,
                    'is_filterable' => (bool) $row->status,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        $this->info("Импортировано характеристик: {$rows->count()}");
    }

    /**
     * Категории-бренды к этому моменту уже удалены import:legacy-brands —
     * привязку к ним переносим на ближайшего существующего предка по дереву
     * старого проекта (TITAN → Аккумуляторы).
     */
    private function importCategoryLinks(): void
    {
        $existingCategoryIds = DB::table('categories')->pluck('id')->flip();
        $legacyParents = DB::connection('legacy')->table('oc_category')->pluck('parent_id', 'category_id');

        $resolveCategory = function (int $categoryId) use ($existingCategoryIds, $legacyParents): ?int {
            while ($categoryId !== 0 && ! isset($existingCategoryIds[$categoryId])) {
                $categoryId = (int) ($legacyParents[$categoryId] ?? 0);
            }

            return $categoryId ?: null;
        };

        $links = DB::connection('legacy')
            ->table('oc_ocfilter_filter_to_category')
            ->select('filter_id', 'category_id')
            ->get()
            ->map(fn ($row) => [
                'attribute_id' => $row->filter_id,
                'category_id' => $resolveCategory((int) $row->category_id),
            ])
            ->filter(fn (array $link) => $link['category_id'] !== null)
            ->unique(fn (array $link) => $link['attribute_id'].'-'.$link['category_id']);

        DB::transaction(function () use ($links): void {
            DB::table('attribute_category')->delete();
            DB::table('attribute_category')->insert($links->values()->all());
        });

        $this->info("Привязано характеристик к категориям: {$links->count()}");
    }

    /**
     * id значений в OCFilter — хэши вроде 1698094399, тащить их как первичный
     * ключ незачем (автоинкремент улетел бы туда же). Заводим свои id и
     * возвращаем карту legacy value_id → новый id для привязки к товарам.
     *
     * @return array<int, int>
     */
    private function importValues(): array
    {
        $rows = DB::connection('legacy')
            ->table('oc_ocfilter_filter_value as v')
            ->join('oc_ocfilter_filter_value_description as vd', function ($join): void {
                $join->on('vd.value_id', '=', 'v.value_id')->on('vd.source', '=', 'v.source');
            })
            ->where('vd.language_id', 1)
            ->select('v.value_id', 'v.filter_id', 'vd.name')
            ->get();

        $valueIdMap = [];

        // sort_order в legacy у всех значений 0 — порядок задаём натуральной
        // сортировкой, чтобы "45 - 54 Ah" шло раньше "100 - 110 Ah".
        foreach ($rows->groupBy('filter_id') as $filterId => $values) {
            $sorted = $values->sort(fn ($a, $b) => strnatcasecmp(trim($a->name), trim($b->name)))->values();

            foreach ($sorted as $position => $row) {
                $value = trim($row->name);

                DB::table('attribute_values')->updateOrInsert(
                    ['attribute_id' => $filterId, 'value' => $value],
                    [
                        'sort_order' => $position,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );

                $valueIdMap[$row->value_id] = DB::table('attribute_values')
                    ->where('attribute_id', $filterId)
                    ->where('value', $value)
                    ->value('id');
            }
        }

        $this->info("Импортировано значений характеристик: {$rows->count()}");

        return $valueIdMap;
    }

    /**
     * @param  array<int, int>  $valueIdMap
     */
    private function importProductLinks(array $valueIdMap): void
    {
        $knownProductIds = DB::table('products')->pluck('id')->flip();

        $links = DB::connection('legacy')
            ->table('oc_ocfilter_filter_value_to_product')
            ->select('value_id', 'product_id')
            ->get()
            ->filter(fn ($row) => isset($valueIdMap[$row->value_id], $knownProductIds[$row->product_id]))
            ->map(fn ($row) => [
                'attribute_value_id' => $valueIdMap[$row->value_id],
                'product_id' => $row->product_id,
            ])
            ->unique(fn (array $link) => $link['attribute_value_id'].'-'.$link['product_id']);

        DB::transaction(function () use ($links): void {
            DB::table('attribute_value_product')->delete();

            foreach ($links->chunk(1000) as $chunk) {
                DB::table('attribute_value_product')->insert($chunk->values()->all());
            }
        });

        $this->info("Привязано значений к товарам: {$links->count()}");
    }
}
