<?php

namespace Database\Factories;

use App\Models\Guide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guide>
 */
class GuideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Как выбрать аккумулятор',
            'slug' => fake()->unique()->slug(2),
            'body' => '<h2 id="polarity">Полярность</h2><p>Текст раздела.</p>',
            'sort_order' => 0,
            'status' => true,
        ];
    }
}
