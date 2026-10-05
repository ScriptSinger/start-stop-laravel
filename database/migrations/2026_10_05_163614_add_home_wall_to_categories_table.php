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
        // «Популярные категории» на главной (модуль uni_category_wall_v2):
        // разделы с картинкой и вручную выбранными ссылками под ними.
        Schema::table('categories', function (Blueprint $table) {
            // Порядок раздела на стене; null — раздел на стене не показывается.
            $table->unsignedInteger('home_wall_sort')->nullable()->after('sort_order');
            // Подкатегория выводится ссылкой на плитке своего раздела.
            $table->boolean('show_on_parent_wall')->default(false)->after('home_wall_sort');
        });

        // Ссылки-производители на плитке раздела (бывшие категории-бренды).
        Schema::create('category_wall_manufacturer', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manufacturer_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['category_id', 'manufacturer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_wall_manufacturer');

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['home_wall_sort', 'show_on_parent_wall']);
        });
    }
};
