<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\SupplierOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierOffer>
 */
class SupplierOfferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'supplier_code' => fake()->unique()->bothify('УТ-########'),
            'name' => fake()->words(3, true),
            'cost' => fake()->numberBetween(1000, 10000),
            'quantity' => fake()->numberBetween(0, 20),
            'seen_at' => now(),
        ];
    }
}
