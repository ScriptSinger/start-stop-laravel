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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturer_id')->nullable()->constrained('manufacturers')->nullOnDelete();
            $table->string('name');
            // SEO из ocStore (oc_product_description): H1 («Аккумулятор ТЮМЕНЬ
            // ASIA 40 Ah П.П.»), <title> и description.
            $table->string('heading')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('slug')->unique();
            $table->string('sku')->nullable();
            // «Код товара» (oc_product.model) — для АКБ это типоразмер вроде
            // 115D31L, по нему ищут. Отдельно от sku: в legacy заполнены
            // разные наборы товаров (model у 1085, sku у 1159).
            $table->string('code')->nullable()->index();
            $table->longText('description')->nullable();
            $table->decimal('price', 15, 4)->default(0);
            // Цена по акции (oc_product_special): на витрине старая цена
            // зачёркнута, наклейка «Ваша скидка: …». Дат действия у акций
            // старого сайта не было — пока не храним.
            $table->decimal('special_price', 15, 4)->nullable();
            $table->unsignedInteger('quantity')->default(0);
            // В старом проекте жили в чужих полях oc_product: остаток у
            // поставщика — в isbn, цена под заказ — в mpn (см. product.twig темы).
            $table->unsignedInteger('supplier_quantity')->default(0);
            $table->decimal('supplier_price', 15, 4)->nullable();
            // Трейд-ин: скидка, если покупатель сдаёт старый АКБ. В OpenCart —
            // опция-галочка «Цена при обмене» с отрицательной надбавкой.
            $table->decimal('trade_in_discount', 15, 4)->nullable();
            // В OpenCart жило текстом «Только самовывоз» в чужом поле jan.
            $table->boolean('is_pickup_only')->default(false);
            $table->string('image')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
