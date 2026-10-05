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
        // Характеристики товаров (ёмкость, полярность, габариты...). Заменяют
        // сразу и oc_product_attribute, и OCFilter старого проекта — там это
        // были дубли одних и тех же данных, берём нормализованный справочник.
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            // Показывать в фильтре каталога (status фильтра в OCFilter).
            $table->boolean('is_filterable')->default(true);
            $table->timestamps();
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['attribute_id', 'value']);
        });

        Schema::create('attribute_value_product', function (Blueprint $table) {
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['attribute_value_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attribute_value_product');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
    }
};
