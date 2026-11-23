<?php

namespace Database\Factories;

use App\Models\ServicePrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicePrice>
 */
class ServicePriceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'capacity_from' => 1,
            'capacity_to' => 10,
            'price' => 100,
            'sort_order' => 0,
        ];
    }
}
