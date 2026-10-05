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
        // Баннеры витрины. На старом сайте — слайды Revolution Slider, где
        // каждый слайд был одной картинкой со ссылкой.
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('position')->index();
            $table->string('title')->nullable();
            // Путь на диске public (catalog/revslider_media_folder/…).
            $table->string('image');
            $table->string('url')->nullable();
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
        Schema::dropIfExists('banners');
    }
};
