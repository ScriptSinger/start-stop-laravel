<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyUrlRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_product_address_redirects_permanently_to_the_new_one(): void
    {
        $product = $this->product('zver-60-ah-o-p');

        $this->get('/zver-60-ah-o-p')->assertStatus(301)->assertRedirect(route('product.show', $product));
    }

    public function test_old_category_path_redirects_by_its_last_segment(): void
    {
        $parent = Category::query()->create(['name' => 'Автотовары', 'slug' => 'avtotovari']);
        $cables = Category::query()->create(['name' => 'Кабели', 'slug' => 'kabeli', 'parent_id' => $parent->id]);

        $this->get('/avtotovari/kabeli')->assertStatus(301)->assertRedirect(route('category.show', $cables));
        $this->get('/avtotovari')->assertStatus(301)->assertRedirect(route('category.show', $parent));
    }

    public function test_old_information_page_redirects(): void
    {
        $page = Page::query()->create(['title' => 'Услуги', 'slug' => 'uslugi', 'status' => true]);

        $this->get('/uslugi')->assertStatus(301)->assertRedirect(route('page.show', $page));
    }

    public function test_query_string_of_old_address_is_dropped(): void
    {
        $product = $this->product('zver-60-ah-o-p');

        $this->get('/zver-60-ah-o-p?tracking=1&limit=100')->assertRedirect(route('product.show', $product));
    }

    public function test_disabled_and_unknown_addresses_stay_not_found(): void
    {
        $this->product('arkhivnyy', ['status' => false]);
        Page::query()->create(['title' => 'Черновик', 'slug' => 'chernovik', 'status' => false]);

        foreach (['/arkhivnyy', '/chernovik', '/net-takogo', '/catalog/view/theme/unishop2/fonts/opensans/opensans-medium.woff2'] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_new_addresses_are_not_redirected(): void
    {
        $product = $this->product('zver-60-ah-o-p');

        $this->get(route('product.show', $product))->assertOk();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $slug, array $attributes = []): Product
    {
        return Product::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'price' => 1,
            'quantity' => 1,
            'status' => true,
            ...$attributes,
        ]);
    }
}
