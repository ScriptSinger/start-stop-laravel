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
        // Редактируемые меню витрины: верхние ссылки, горизонтальное меню
        // (Акции, Услуги…) и колонки подвала. В OpenCart жили в настройках
        // темы UniShop2 (toplinks, header.headerlinks2, footer_columns).
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('location')->index();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('title');
            // Путь на сайте (/page/trade-in) или внешняя ссылка; у заголовка
            // колонки подвала может не быть.
            $table->string('url')->nullable();
            // Класс иконки Font Awesome, например "fas fa-tools".
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
