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
        // Подборки товаров на главной («Рекомендуем», «Акции») — на старом
        // сайте списки товаров в модулях uni_five_in_one_v2.
        Schema::create('product_selection', function (Blueprint $table) {
            $table->string('selection');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['selection', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_selection');
    }
};
