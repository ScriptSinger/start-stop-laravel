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
            // В старом проекте жили в чужих полях oc_product: остаток у
            // поставщика — в isbn, цена под заказ — в mpn (см. product.twig темы).
            $table->unsignedInteger('supplier_quantity')->default(0)->after('quantity');
            $table->decimal('supplier_price', 15, 4)->nullable()->after('supplier_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['supplier_quantity', 'supplier_price']);
        });
    }
};
