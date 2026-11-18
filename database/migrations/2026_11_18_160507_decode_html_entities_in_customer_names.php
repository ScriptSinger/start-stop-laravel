<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Импорт из OpenCart тянул имена вместе с HTML-сущностями («Al&#39;mir»).
 * Приводим уже загруженные имена покупателей и заказов к обычному тексту.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([['customers', 'name'], ['orders', 'customer_name']] as [$table, $column]) {
            DB::table($table)
                ->where($column, 'like', '%&%;%')
                ->select(['id', $column])
                ->orderBy('id')
                ->each(function (object $row) use ($table, $column): void {
                    DB::table($table)->where('id', $row->id)->update([
                        $column => html_entity_decode($row->{$column}, ENT_QUOTES | ENT_HTML5),
                    ]);
                });
        }
    }

    public function down(): void
    {
        // Данные не восстанавливаем: исходные сущности были мусором импорта.
    }
};
