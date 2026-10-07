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
        Schema::create('battery_fitments', function (Blueprint $table) {
            $table->id();
            $table->string('brand')->index();
            $table->string('model')->index();
            $table->string('generation')->nullable();
            // Двигатель внутри поколения — разным моторам бывают нужны разные АКБ
            // (из выгрузки подбора podbor.xlsx).
            $table->string('engine')->nullable();
            $table->text('capacity')->nullable();
            // Одна или несколько через запятую: «Обратная, Универсальная».
            $table->text('polarity')->nullable();
            $table->text('dims')->nullable();
            // Тип клемм: standard, thin, side, bolt, threaded (shop.battery_fitment.terminal_values).
            $table->string('terminals', 20)->nullable();
            $table->string('image')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('battery_fitments');
    }
};
