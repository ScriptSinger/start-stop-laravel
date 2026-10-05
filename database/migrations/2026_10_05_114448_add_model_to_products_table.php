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
        Schema::table('products', function (Blueprint $table) {
            // «Код товара» (oc_product.model) — для АКБ это типоразмер вроде
            // 115D31L, по нему ищут. Отдельно от sku: в legacy заполнены
            // разные наборы товаров (model у 1085, sku у 1159).
            $table->string('code')->nullable()->after('sku')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
