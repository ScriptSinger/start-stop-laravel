<?php

namespace Database\Factories;

use App\Models\BrandPage;
use App\Models\Category;
use App\Models\Manufacturer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrandPage>
 */
class BrandPageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::query()->create(['name' => fake()->unique()->word()])->id,
            'manufacturer_id' => Manufacturer::query()->create(['name' => fake()->unique()->word()])->id,
            'heading' => null,
            'meta_title' => null,
            'meta_description' => null,
            'description' => null,
        ];
    }
}
