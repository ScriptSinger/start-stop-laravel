<?php

namespace Database\Factories;

use App\Models\StoreReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreReview>
 */
class StoreReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author' => fake()->name(),
            'source' => 'yandex',
            'rating' => 5,
            'body' => fake()->sentence(),
            'reviewed_at' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
