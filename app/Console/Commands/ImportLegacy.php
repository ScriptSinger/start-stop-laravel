<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('import:legacy')]
#[Description('Полный импорт данных из старого проекта: категории → производители → товары → бренды → характеристики → АКБ → заказы → страницы')]
class ImportLegacy extends Command
{
    public function handle(): int
    {
        $this->call('import:legacy-categories');
        $this->call('import:legacy-manufacturers');
        $this->call('import:legacy-products');
        $this->call('import:legacy-brands');
        $this->call('import:legacy-attributes');
        $this->call('import:legacy-battery');
        $this->call('import:legacy-orders');
        $this->call('import:legacy-pages');

        $this->newLine();
        $this->components->info('Сверка количества строк (legacy → новая таблица):');

        // categories/manufacturers не сверяем 1:1 — import:legacy-brands
        // намеренно превращает категории-бренды в производителей.
        $checks = [
            ['oc_product', 'products'],
            ['oc_ocfilter_filter', 'attributes'],
            ['oc_ocfilter_filter_value', 'attribute_values'],
            ['oc_ocfilter_filter_value_to_product', 'attribute_value_product'],
            ['oc_battery_base', 'battery_fitments'],
            ['oc_customer', 'customers'],
            ['oc_order', 'orders'],
            ['oc_information', 'pages'],
        ];

        $rows = [];
        foreach ($checks as [$legacyTable, $newTable]) {
            $legacyCount = DB::connection('legacy')->table($legacyTable)->count();
            $newCount = DB::table($newTable)->count();
            $rows[] = [$legacyTable.' → '.$newTable, $legacyCount, $newCount, $legacyCount === $newCount ? 'OK' : 'MISMATCH'];
        }

        $this->table(['Таблица', 'legacy', 'новая', 'статус'], $rows);

        return self::SUCCESS;
    }
}
