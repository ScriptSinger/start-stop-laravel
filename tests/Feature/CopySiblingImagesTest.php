<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CopySiblingImagesTest extends TestCase
{
    use RefreshDatabase;

    private Category $batteries;

    private Category $trucks;

    private Category $brushes;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->batteries = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori']);
        $this->trucks = Category::query()->create(['name' => 'Грузовые аккумуляторы', 'slug' => 'gruzovye']);
        $this->brushes = Category::query()->create(['name' => 'Стеклоочистители', 'slug' => 'stekloochistiteli']);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->donor('EDCON 77Ah О.П.', 'catalog/edcon.jpg');
        $new = $this->newProduct('EDCON 63Ah О.П.');

        $this->artisan('products:copy-sibling-images')->expectsOutputToContain('Пробный прогон')->assertSuccessful();

        $this->assertNull($new->fresh()->image);
    }

    public function test_battery_gets_image_of_the_same_series(): void
    {
        $this->donor('EDCON 77Ah О.П.', 'catalog/edcon.jpg');
        $this->donor('EDCON AGM 70Ah О.П.', 'catalog/edcon-agm.jpg');
        $plain = $this->newProduct('EDCON 63Ah О.П.');
        $agm = $this->newProduct('EDCON AGM 80Ah П.П.');
        $other = $this->newProduct('ROCKET 60Ah О.П.');

        $this->artisan('products:copy-sibling-images', ['--force' => true])->assertSuccessful();

        $this->assertSame('catalog/edcon.jpg', $plain->fresh()->image);
        $this->assertSame('catalog/edcon-agm.jpg', $agm->fresh()->image);
        $this->assertNull($other->fresh()->image);
    }

    public function test_closest_capacity_and_same_category_decide_the_donor(): void
    {
        $this->donor('SOLO 77Ah О.П.', 'catalog/solo-car.jpg');
        $this->donor('SOLO 190Ah П.П.', 'catalog/solo-truck.jpg', $this->trucks);
        $near = $this->donor('SOLO 60Ah О.П.', 'catalog/solo-60.jpg');
        $car = $this->newProduct('SOLO 66Ah О.П.');
        $truck = $this->newProduct('SOLO 225Ah О.П.', $this->trucks);

        $this->artisan('products:copy-sibling-images', ['--force' => true])->assertSuccessful();

        $this->assertSame($near->image, $car->fresh()->image);
        $this->assertSame('catalog/solo-truck.jpg', $truck->fresh()->image);
    }

    public function test_other_goods_need_the_same_words_and_at_least_three_of_them(): void
    {
        $this->donor('Щетка стеклоочистителя ALCA 13 (330мм) графит', 'catalog/alca.jpg', $this->brushes);
        $this->donor('Лампа H7', 'catalog/lamp.jpg', $this->brushes);
        $same = $this->newProduct('Щетка стеклоочистителя ALCA 17 (430мм) графит', $this->brushes);
        $short = $this->newProduct('Лампа H4', $this->brushes);

        $this->artisan('products:copy-sibling-images', ['--force' => true])->assertSuccessful();

        $this->assertSame('catalog/alca.jpg', $same->fresh()->image);
        $this->assertNull($short->fresh()->image);
    }

    public function test_existing_images_and_products_without_onec_code_are_not_touched(): void
    {
        $this->donor('EDCON 77Ah О.П.', 'catalog/edcon.jpg');
        $withImage = $this->newProduct('EDCON 63Ah О.П.');
        Storage::disk('public')->put('catalog/own.jpg', 'x');
        $withImage->update(['image' => 'catalog/own.jpg']);
        $local = Product::query()->create(['name' => 'EDCON 70Ah О.П.', 'slug' => 'edcon-70', 'price' => 1]);
        $local->categories()->attach($this->batteries);

        $this->artisan('products:copy-sibling-images', ['--force' => true])->assertSuccessful();

        $this->assertSame('catalog/own.jpg', $withImage->fresh()->image);
        $this->assertNull($local->fresh()->image);
    }

    public function test_donor_with_missing_file_is_ignored(): void
    {
        $donor = Product::query()->create(['name' => 'EDCON 77Ah О.П.', 'slug' => 'edcon-77', 'price' => 1, 'image' => 'catalog/missing.jpg']);
        $donor->categories()->attach($this->batteries);
        $new = $this->newProduct('EDCON 63Ah О.П.');

        $this->artisan('products:copy-sibling-images', ['--force' => true])->assertSuccessful();

        $this->assertNull($new->fresh()->image);
    }

    private function donor(string $name, string $image, ?Category $category = null): Product
    {
        Storage::disk('public')->put($image, 'x');
        $product = Product::query()->create(['name' => $name, 'slug' => str($name)->slug()->value().'-d', 'price' => 1, 'status' => true, 'image' => $image]);
        $product->categories()->attach($category ?? $this->batteries);

        return $product;
    }

    private function newProduct(string $name, ?Category $category = null): Product
    {
        $product = Product::query()->create(['name' => $name, 'slug' => str($name)->slug()->value().'-n', 'price' => 1, 'external_id' => 'РТ-'.str($name)->slug(), 'status' => false]);
        $product->categories()->attach($category ?? $this->batteries);

        return $product;
    }
}
