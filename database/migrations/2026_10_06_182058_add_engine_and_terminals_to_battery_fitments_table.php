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
        Schema::table('battery_fitments', function (Blueprint $table) {
            // Из исходной выгрузки подбора (podbor.xlsx): двигатель внутри
            // поколения — разным моторам бывают нужны разные АКБ — и тип клемм.
            $table->string('engine')->nullable()->after('generation');
            $table->string('terminals', 20)->nullable()->after('dims');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('battery_fitments', function (Blueprint $table) {
            $table->dropColumn(['engine', 'terminals']);
        });
    }
};
