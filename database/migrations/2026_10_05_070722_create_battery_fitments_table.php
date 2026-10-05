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
            $table->text('capacity')->nullable();
            $table->text('polarity')->nullable();
            $table->text('dims')->nullable();
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
