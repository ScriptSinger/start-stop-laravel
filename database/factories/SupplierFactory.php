<?php

namespace Database\Factories;

use App\Enums\SupplierPriceFormat;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'format' => SupplierPriceFormat::AkbAuto,
            'markup_percent' => 20,
            'lead_working_days' => 2,
            'min_quantity' => 5,
            'ignore_quantity' => false,
            'is_active' => true,
        ];
    }
}
