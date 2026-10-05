<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('import:legacy')]
#[Description('Полный импорт данных из старого проекта: категории → производители → товары (с брендами) → характеристики → АКБ → заказы → страницы')]
class ImportLegacy extends Command
{
    public function handle(): int
    {
        $this->call('import:legacy-categories');
        $this->call('import:legacy-manufacturers');
        $this->call('import:legacy-products');
        $this->call('import:legacy-attributes');
        $this->call('import:legacy-battery');
        $this->call('import:legacy-orders');
        $this->call('import:legacy-pages');

        $this->newLine();
        $this->components->info('Сверка количества строк (legacy → новая таблица):');

        // categories/manufacturers не сверяем 1:1 — import:legacy-brands
        // намеренно превращает категории-бренды в производителей.
        // Третий элемент — как считать ожидаемое число строк в legacy, если
        // импорт намеренно склеивает дубли (по умолчанию — все строки таблицы).
        $checks = [
            ['oc_product', 'products'],
            ['oc_ocfilter_filter', 'attributes'],
            ['oc_ocfilter_filter_value', 'attribute_values', fn (): int => $this->distinctLegacyAttributeValues()],
            ['oc_ocfilter_filter_value_to_product', 'attribute_value_product'],
            ['oc_battery_base', 'battery_fitments'],
            ['oc_customer', 'customers'],
            ['oc_order', 'orders'],
            ['oc_information', 'pages'],
        ];

        $rows = [];
        foreach ($checks as $check) {
            [$legacyTable, $newTable] = $check;
            $legacyCount = isset($check[2])
                ? $check[2]()
                : DB::connection('legacy')->table($legacyTable)->count();
            $newCount = DB::table($newTable)->count();
            $rows[] = [$legacyTable.' → '.$newTable, $legacyCount, $newCount, $legacyCount === $newCount ? 'OK' : 'MISMATCH'];
        }

        $this->table(['Таблица', 'legacy', 'новая', 'статус'], $rows);

        return self::SUCCESS;
    }

    /**
     * import:legacy-attributes склеивает значения с одинаковым текстом внутри
     * характеристики (в OCFilter, например, «Renault RN700» заведён дважды),
     * а уникальный индекс в MySQL не различает регистр — считаем так же.
     */
    private function distinctLegacyAttributeValues(): int
    {
        return (int) DB::connection('legacy')
            ->table('oc_ocfilter_filter_value as v')
            ->join('oc_ocfilter_filter_value_description as vd', function ($join): void {
                $join->on('vd.value_id', '=', 'v.value_id')->on('vd.source', '=', 'v.source');
            })
            ->where('vd.language_id', 1)
            ->selectRaw('COUNT(DISTINCT v.filter_id, LOWER(TRIM(vd.name))) as aggregate')
            ->value('aggregate');
    }
}
