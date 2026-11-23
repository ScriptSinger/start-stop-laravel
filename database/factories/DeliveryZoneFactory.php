<?php

namespace Database\Factories;

use App\Models\DeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryZone>
 */
class DeliveryZoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Район '.fake()->unique()->numberBetween(1, 9999),
            'price' => 0,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function priced(int $price): static
    {
        return $this->state(['price' => $price]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
