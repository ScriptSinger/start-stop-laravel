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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            // SEO из ocStore (oc_category_description): заголовок H1 на
            // странице («Автомобильные аккумуляторы»), <title> и description.
            $table->string('heading')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            // Иконка в меню категорий: картинка (catalog/icons/…png) или класс
            // Font Awesome ("fas fa-tools") — как в настройках UniShop2.
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            // «Популярные категории» на главной (модуль uni_category_wall_v2):
            // порядок раздела на стене; null — раздел на стене не показывается.
            $table->unsignedInteger('home_wall_sort')->nullable();
            // Подкатегория выводится ссылкой на плитке своего раздела.
            $table->boolean('show_on_parent_wall')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
