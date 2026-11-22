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
        // Типы корпусов аккумуляторов и скидка за сдачу старого АКБ. Тип — корпус:
        // диапазон размеров в мм (длина × ширина × высота) или код в названии значения
        // «Габаритов» (Груз A). Товар попадает в тип по своим «Габаритам». Единственное место, где задаётся
        // сумма скидки: у товара своей копии нет.
        Schema::create('trade_in_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('amount', 12, 2)->nullable();
            $table->unsignedSmallInteger('length_min')->nullable();
            $table->unsignedSmallInteger('length_max')->nullable();
            $table->unsignedSmallInteger('width_min')->nullable();
            $table->unsignedSmallInteger('width_max')->nullable();
            $table->unsignedSmallInteger('height_min')->nullable();
            $table->unsignedSmallInteger('height_max')->nullable();
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
        Schema::dropIfExists('trade_in_types');
    }
};
