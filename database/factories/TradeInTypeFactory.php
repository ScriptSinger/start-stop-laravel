<?php

namespace Database\Factories;

use App\Models\TradeInType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TradeInType>
 */
class TradeInTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'T'.fake()->unique()->numberBetween(100, 999),
            'name' => 'Тестовый тип',
            'amount' => 1000,
            'is_active' => true,
        ];
    }

    /**
     * Размеры в мм: диапазон ±$tolerance вокруг указанных.
     */
    public function dimensions(int $length, int $width, int $height, int $tolerance = 2): static
    {
        return $this->state([
            'length_min' => $length - $tolerance, 'length_max' => $length + $tolerance,
            'width_min' => $width - $tolerance, 'width_max' => $width + $tolerance,
            'height_min' => $height - $tolerance, 'height_max' => $height + $tolerance,
        ]);
    }
}
