<?php

namespace Database\Factories;

use App\Enums\ServicePriceType;
use App\Models\ServiceOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOffer>
 */
class ServiceOfferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => 'service-'.fake()->unique()->numberBetween(1, 9999),
            'title' => 'Тестовая услуга',
            'icon' => 'fa-tools',
            'description' => 'Описание тестовой услуги.',
            'price_type' => ServicePriceType::Free,
            'price' => null,
            'terms' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function priced(ServicePriceType $type, ?int $price = null): static
    {
        return $this->state(['price_type' => $type, 'price' => $price]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
