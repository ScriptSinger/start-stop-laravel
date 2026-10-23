<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Services\Supplier\SupplierPriceImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('suppliers:import {supplier : Id или название поставщика} {file : Прайс (.xlsx или .csv с разделителем «;»)} {--force : Записать в базу; без флага — только отчёт}')]
#[Description('Загрузить прайс поставщика: обновить предложения и пересчитать «под заказ» у товаров')]
class ImportSupplierPrices extends Command
{
    /**
     * Без --force ничего не пишет: выполняется та же загрузка, но откатывается.
     * Перед записью: php artisan db:backup.
     */
    public function handle(SupplierPriceImporter $importer): int
    {
        $supplier = Supplier::query()
            ->where('id', $this->argument('supplier'))
            ->orWhere('name', $this->argument('supplier'))
            ->first();

        if ($supplier === null) {
            $this->error('Поставщик не найден.');

            return self::FAILURE;
        }

        $report = $importer->run($supplier, $this->argument('file'), (bool) $this->option('force'));

        $this->table(['Показатель', 'Число'], [
            ['Строк в прайсе', $report->rows],
            ['Повторов кода (пропущено)', $report->duplicates],
            ['Новых предложений', $report->created],
            ['…из них привязано по названию', $report->linkedByName],
            ['Изменилась закупка', $report->costChanged],
            ['Пропало из прайса', $report->vanished],
            ['Без товара на сайте', $report->unlinked->count()],
            ['Станет под заказ (новых)', $report->productsAdded],
            ['Изменится цена или поставщик под заказ', $report->productsRepriced],
            ['Перестанет быть под заказ', $report->productsCleared],
        ]);

        $this->line($this->option('force') ? 'Записано.' : 'Пробный прогон: в базу ничего не записано. Для записи добавьте --force.');

        return self::SUCCESS;
    }
}
