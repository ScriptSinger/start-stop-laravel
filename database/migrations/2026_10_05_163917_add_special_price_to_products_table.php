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
            // Цена по акции (oc_product_special): на витрине старая цена
            // зачёркнута, наклейка «Ваша скидка: …». Дат действия у акций
            // старого сайта не было — пока не храним.
            $table->decimal('special_price', 15, 4)->nullable()->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('special_price');
        });
    }
};
