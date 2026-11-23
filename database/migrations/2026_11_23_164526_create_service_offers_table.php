<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Карточки страницы «Услуги». Раньше жили в config/shop.php, теперь правятся
     * в админке: тип цены (бесплатно, фикс, «от», по договорённости, по телефону)
     * плюс необязательный текст условий.
     */
    public function up(): void
    {
        Schema::create('service_offers', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('icon');
            $table->text('description');
            $table->string('price_type')->default('free');
            $table->unsignedInteger('price')->nullable();
            $table->text('terms')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $offers = [
            ['battery-check', 'Диагностика аккумулятора', 'fa-car-battery', 'Проверим состояние аккумулятора и поможем понять, требуется ли зарядка, обслуживание или замена.', 'free', null],
            ['maintenance', 'Обслуживание и зарядка АКБ', 'fa-charging-station', 'Обслужим и зарядим аккумулятор. Уточним необходимые работы после проверки его состояния.', 'free', 'для наших клиентов в течение гарантийного периода'],
            ['car-check', 'Диагностика автомобиля', 'fa-car', 'Проверим генератор, стартер и утечку тока — возможные причины проблем с запуском и разряда аккумулятора.', 'free', 'при покупке аккумулятора в нашем магазине'],
            ['installation', 'Установка аккумулятора', 'fa-tools', 'Установим аккумулятор на ваш автомобиль. Перед работой согласуем стоимость.', 'negotiable', null],
            ['express-check', 'Экспресс-проверка АКБ', 'fa-bolt', 'Проверим аккумулятор непосредственно на автомобиле, без снятия.', 'by_phone', null],
        ];

        foreach ($offers as $index => [$slug, $title, $icon, $description, $priceType, $terms]) {
            DB::table('service_offers')->insert([
                'slug' => $slug,
                'title' => $title,
                'icon' => $icon,
                'description' => $description,
                'price_type' => $priceType,
                'terms' => $terms,
                'sort_order' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_offers');
    }
};
