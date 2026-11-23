<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Районы Уфы и цена курьерской доставки (страница «Доставка и оплата»);
     * 0 — бесплатно. Раньше жили в config/shop.php, теперь правятся в админке.
     */
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('price')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $zones = [
            'Сипайлово' => 0,
            'Зелёная роща' => 0,
            'Центр' => 0,
            'Проспект Октября' => 0,
            'Инорс / Черниковка' => 0,
            'Шакша' => 500,
            'Дема' => 500,
            'Затон' => 500,
        ];

        $order = 0;

        foreach ($zones as $name => $price) {
            DB::table('delivery_zones')->insert([
                'name' => $name,
                'price' => $price,
                'sort_order' => ++$order,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
    }
};
