<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Manufacturer;
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

    public function test_old_brand_inside_category_redirects_to_the_category_filtered_by_manufacturer(): void
    {
        $batteries = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori']);
        $titan = Manufacturer::query()->create(['name' => 'TITAN', 'slug' => 'titan']);

        $expected = route('category.show', ['category' => $batteries, 'manufacturer' => [$titan->id]]);

        $this->get('/akkumulyatori/titan')->assertStatus(301)->assertRedirect($expected);
        // У старого сайта у марки в разделе был числовой суффикс.
        $this->get('/akkumulyatori/titan-1')->assertStatus(301)->assertRedirect($expected);
    }

    public function test_old_brand_inside_category_redirects_to_the_brand_page_when_it_exists(): void
    {
        $batteries = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori']);
        $titan = Manufacturer::query()->create(['name' => 'TITAN', 'slug' => 'titan']);

        foreach (['60', '75', '90'] as $capacity) {
            Product::query()
                ->create(['name' => "TITAN {$capacity}", 'slug' => "titan-{$capacity}", 'manufacturer_id' => $titan->id, 'price' => 7000, 'status' => true])
                ->categories()
                ->attach($batteries);
        }

        $expected = route('category.manufacturer', [$batteries, $titan]);

        $this->get('/akkumulyatori/titan')->assertStatus(301)->assertRedirect($expected);
        $this->get('/akkumulyatori/titan-1')->assertStatus(301)->assertRedirect($expected);
        $this->get('/akkumulyatori/titan?page=3')->assertStatus(301)->assertRedirect($expected.'?page=3');
    }

    public function test_exact_manufacturer_slug_wins_over_the_trimmed_one(): void
    {
        $trucks = Category::query()->create(['name' => 'Грузовые', 'slug' => 'gruzovie-akkumulyatori']);
        Manufacturer::query()->create(['name' => 'DELKOR', 'slug' => 'delkor']);
        $exact = Manufacturer::query()->create(['name' => 'DELKOR 2', 'slug' => 'delkor-2']);

        $this->get('/gruzovie-akkumulyatori/delkor-2')
            ->assertRedirect(route('category.show', ['category' => $trucks, 'manufacturer' => [$exact->id]]));
    }

    public function test_page_number_of_an_old_category_address_is_kept(): void
    {
        $category = Category::query()->create(['name' => 'Автотовары', 'slug' => 'avtotovari']);

        $this->get('/avtotovari?page=3')->assertRedirect(route('category.show', ['category' => $category, 'page' => 3]));
        $this->get('/avtotovari?page=1')->assertRedirect(route('category.show', $category));
    }

    public function test_unknown_brand_or_brand_outside_a_category_is_not_found(): void
    {
        Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori']);
        Manufacturer::query()->create(['name' => 'TITAN', 'slug' => 'titan']);

        $this->get('/akkumulyatori/net-takoy-marki')->assertNotFound();
        $this->get('/net-takogo-razdela/titan')->assertNotFound();
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

    public function test_old_index_php_addresses_redirect_by_the_ids_kept_from_the_import(): void
    {
        $product = $this->product('zver-60-ah-o-p');
        $parent = Category::query()->create(['name' => 'Автотовары', 'slug' => 'avtotovari']);
        $child = Category::query()->create(['name' => 'Кабели', 'slug' => 'kabeli', 'parent_id' => $parent->id]);
        $page = Page::query()->create(['title' => 'Услуги', 'slug' => 'uslugi', 'status' => true]);

        $this->get("/index.php?route=product/product&path=1&product_id={$product->id}")->assertStatus(301)->assertRedirect(route('product.show', $product));
        $this->get("/index.php?route=product/category&path={$parent->id}_{$child->id}")->assertStatus(301)->assertRedirect(route('category.show', $child));
        $this->get("/index.php?route=information/information&information_id={$page->id}")->assertStatus(301)->assertRedirect(route('page.show', $page));
        $this->get("/index.php?route=information/information/agree&information_id={$page->id}")->assertStatus(301)->assertRedirect(route('page.show', $page));
        $this->get('/index.php?route=information/contact')->assertStatus(301)->assertRedirect(route('contact'));
    }

    public function test_index_php_without_a_route_goes_to_the_home_page(): void
    {
        // Адрес — от корня сайта, а не /index.php: иначе редирект зациклился бы.
        $this->get('/index.php')->assertStatus(301)->assertRedirect('http://localhost/');
        $this->get('/index.php?route=common/home')->assertStatus(301)->assertRedirect('http://localhost/');
    }

    public function test_index_php_with_a_missing_object_or_an_unknown_route_is_not_found(): void
    {
        $inactive = $this->product('arkhivnyy', ['status' => false]);

        $this->get("/index.php?route=product/product&product_id={$inactive->id}")->assertNotFound();
        $this->get('/index.php?route=product/product&product_id=999999')->assertNotFound();
        $this->get('/index.php?route=account/login')->assertNotFound();
    }

    public function test_home_page_itself_is_not_redirected(): void
    {
        $this->get('/')->assertOk();
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
