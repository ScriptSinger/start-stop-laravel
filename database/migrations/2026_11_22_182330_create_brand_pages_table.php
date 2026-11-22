<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Свои тексты страницы «раздел + производитель»: H1, мета-теги и описание.
     * Пустые поля сайт заменяет шаблоном.
     */
    public function up(): void
    {
        Schema::create('brand_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manufacturer_id')->constrained()->cascadeOnDelete();
            $table->string('heading')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->longText('description')->nullable();
            $table->timestamps();

            $table->unique(['category_id', 'manufacturer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_pages');
    }
};
