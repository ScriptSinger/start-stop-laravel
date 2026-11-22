<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Manufacturer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_manufacturer_gets_slug_from_name_when_not_given(): void
    {
        $manufacturer = Manufacturer::query()->create(['name' => 'Alpha Line']);

        $this->assertSame('alpha-line', $manufacturer->slug);
    }

    public function test_category_gets_slug_from_name_when_not_given(): void
    {
        $category = Category::query()->create(['name' => 'Моторное масло']);

        $this->assertNotSame('', $category->slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $category->slug);
    }

    public function test_name_without_letters_still_gets_a_unique_slug(): void
    {
        $first = Manufacturer::query()->create(['name' => '***']);
        $second = Manufacturer::query()->create(['name' => '!!!']);

        $this->assertNotSame('', $first->slug);
        $this->assertNotSame($first->slug, $second->slug);
    }

    public function test_explicit_slug_is_kept(): void
    {
        $manufacturer = Manufacturer::query()->create(['name' => 'TITAN', 'slug' => 'titan-old']);

        $this->assertSame('titan-old', $manufacturer->slug);
    }

    public function test_same_name_gets_a_unique_slug(): void
    {
        Manufacturer::query()->create(['name' => 'Delkor']);
        $second = Manufacturer::query()->create(['name' => 'Delkor']);

        $this->assertSame('delkor-2', $second->slug);
    }

    public function test_slug_of_existing_record_does_not_change_on_rename(): void
    {
        $manufacturer = Manufacturer::query()->create(['name' => 'Zver']);
        $manufacturer->update(['name' => 'Zver Premium']);

        $this->assertSame('zver', $manufacturer->fresh()->slug);
    }
}
