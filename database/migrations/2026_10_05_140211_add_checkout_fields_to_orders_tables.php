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
        Schema::table('orders', function (Blueprint $table) {
            // Снимок подписи способа доставки, как и payment_method.
            $table->string('delivery_method')->nullable()->after('payment_method');
            $table->text('comment')->nullable()->after('shipping_address');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Скидка по трейд-ину на единицу товара; null — без обмена.
            // price в позиции — уже с учётом этой скидки.
            $table->decimal('trade_in_discount', 15, 4)->nullable()->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('trade_in_discount');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_method', 'comment']);
        });
    }
};
