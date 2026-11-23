<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Цены на обслуживание аккумулятора по ёмкости (страница «Обслуживание»).
     * Раньше жили таблицей в тексте страницы, теперь правятся в админке, а
     * таблица на сайте строится из них (метка [[block:service-prices]]).
     */
    public function up(): void
    {
        Schema::create('service_prices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('capacity_from');
            $table->unsignedSmallInteger('capacity_to');
            $table->unsignedInteger('price');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $prices = [
            [3, 30, 200],
            [35, 65, 500],
            [66, 78, 600],
            [80, 85, 700],
            [90, 110, 800],
            [132, 140, 900],
            [180, 230, 1000],
        ];

        foreach ($prices as $index => [$from, $to, $price]) {
            DB::table('service_prices')->insert([
                'capacity_from' => $from,
                'capacity_to' => $to,
                'price' => $price,
                'sort_order' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_prices');
    }
};
