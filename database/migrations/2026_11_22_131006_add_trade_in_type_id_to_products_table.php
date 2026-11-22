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
        // Тип корпуса товара — отдельное поле, а не «Габариты» (там размеры, а у
        // грузовых ещё и ёмкость в названии). Скидка за сдачу старого АКБ берётся
        // только из типа; пусто — обмена нет.
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('trade_in_type_id')->nullable()->after('manufacturer_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trade_in_type_id');
        });
    }
};
