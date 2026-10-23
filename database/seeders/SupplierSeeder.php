<?php

namespace Database\Seeders;

use App\Enums\SupplierPriceFormat;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Поставщики с прайсами. Запускать отдельно: php artisan db:seed --class=SupplierSeeder.
     * Уже существующих не трогает, поэтому настройки из админки не затираются.
     * Наценка 20% — стартовая, её задают в карточке поставщика.
     */
    public function run(): void
    {
        $suppliers = [
            ['name' => 'АКБ АВТО', 'format' => SupplierPriceFormat::AkbAuto, 'lead_working_days' => 2, 'ignore_quantity' => false],
            ['name' => 'СибЭлектроИсточник', 'format' => SupplierPriceFormat::SibElectro, 'lead_working_days' => 3, 'ignore_quantity' => false],
            ['name' => 'АКБ Дриллинг', 'format' => SupplierPriceFormat::Drilling, 'lead_working_days' => 3, 'ignore_quantity' => true],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::query()->firstOrCreate(['name' => $supplier['name']], [
                ...$supplier,
                'markup_percent' => 20,
                'min_quantity' => 5,
                'is_active' => true,
            ]);
        }
    }
}
