<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Служебные характеристики (диапазоны «55 - 65 Ah» для фильтра и подбора)
        // не показываем покупателю на карточке и в сравнении, но оставляем в
        // фильтре и админке. По умолчанию показываются все.
        Schema::table('attributes', function (Blueprint $table) {
            $table->boolean('show_on_card')->default(true)->after('is_filterable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn('show_on_card');
        });
    }
};
