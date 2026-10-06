<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Тестовый покупатель для входа на витрину (пароль — «password»).
     * Каталог и заказы заполняет импорт со старого сайта: php artisan import:legacy.
     */
    public function run(): void
    {
        Customer::factory()->create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
        ]);
    }
}
