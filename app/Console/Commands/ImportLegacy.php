<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('import:legacy')]
#[Description('Полный импорт данных из старого проекта: категории → производители → товары → АКБ → заказы')]
class ImportLegacy extends Command
{
    public function handle(): int
    {
        $this->call('import:legacy-categories');
        $this->call('import:legacy-manufacturers');
        $this->call('import:legacy-products');
        $this->call('import:legacy-battery');
        $this->call('import:legacy-orders');

        $this->newLine();
        $this->components->info('Сверка количества строк (legacy → новая таблица):');

        $checks = [
            ['oc_category', 'categories'],
            ['oc_manufacturer', 'manufacturers'],
            ['oc_product', 'products'],
            ['oc_battery_base', 'battery_fitments'],
            ['oc_customer', 'customers'],
            ['oc_order', 'orders'],
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
