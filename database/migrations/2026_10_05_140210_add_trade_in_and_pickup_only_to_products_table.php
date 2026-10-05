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
            // Трейд-ин: скидка, если покупатель сдаёт старый АКБ. В OpenCart —
            // опция-галочка «Цена при обмене» с отрицательной надбавкой.
            $table->decimal('trade_in_discount', 15, 4)->nullable()->after('supplier_price');
            // В OpenCart жило текстом «Только самовывоз» в чужом поле jan.
            $table->boolean('is_pickup_only')->default(false)->after('trade_in_discount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['trade_in_discount', 'is_pickup_only']);
        });
    }
};
