<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManufacturerPageTest extends TestCase
{
    use RefreshDatabase;

    private Category $batteries;

    private Manufacturer $titan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->batteries = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori']);
        $this->titan = Manufacturer::query()->create(['name' => 'TITAN', 'slug' => 'titan']);

        foreach (['60', '75', '90'] as $capacity) {
            $this->product("TITAN {$capacity}", $this->titan, $this->batteries);
        }

        $this->product('VARTA 60', Manufacturer::query()->create(['name' => 'VARTA', 'slug' => 'varta']), $this->batteries);
    }

    public function test_brand_page_shows_only_products_of_the_manufacturer(): void
    {
        $this->get(route('category.manufacturer', [$this->batteries, $this->titan]))
            ->assertOk()
            ->assertSee('TITAN 60')
            ->assertSee('TITAN 90')
            ->assertDontSee('VARTA 60')
            ->assertSee('<h1>Аккумуляторы TITAN</h1>', false);
    }

    public function test_brand_page_has_own_title_description_and_canonical(): void
    {
        $url = route('category.manufacturer', [$this->batteries, $this->titan]);

        $this->get($url)
            ->assertSee('<title>Аккумуляторы TITAN — купить в Уфе', false)
            ->assertSee('<link rel="canonical" href="'.$url.'">', false)
            ->assertDontSee('noindex');
    }

    public function test_sorting_on_brand_page_keeps_canonical_on_the_brand_page(): void
    {
        $url = route('category.manufacturer', [$this->batteries, $this->titan]);

        $this->get($url.'?sort=p.price&limit=25')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.$url.'">', false);
    }

    public function test_manufacturer_with_too_few_products_has_no_page(): void
    {
        $varta = Manufacturer::query()->where('slug', 'varta')->firstOrFail();

        $this->get(route('category.manufacturer', [$this->batteries, $varta]))->assertNotFound();
    }

    public function test_category_outside_the_config_list_has_no_brand_pages(): void
    {
        $oils = Category::query()->create(['name' => 'Масла', 'slug' => 'motornoe-maslo']);

        foreach (['1', '2', '3'] as $number) {
            $this->product("Масло {$number}", $this->titan, $oils);
        }

        $this->get(route('category.manufacturer', [$oils, $this->titan]))->assertNotFound();
    }

    public function test_count_address_is_not_taken_for_a_manufacturer(): void
    {
        $this->getJson(route('category.count', $this->batteries))->assertOk()->assertJsonStructure(['count']);
    }

    public function test_unknown_manufacturer_gives_404(): void
    {
        $this->get('/category/akkumulyatori/net-takogo')->assertNotFound();
    }

    public function test_single_manufacturer_filter_redirects_to_the_brand_page(): void
    {
        $this->get(route('category.show', ['category' => $this->batteries, 'manufacturer' => [$this->titan->id], 'sort' => 'p.price', 'page' => 2]))
            ->assertStatus(301)
            ->assertRedirect(route('category.manufacturer', [$this->batteries, $this->titan, 'sort' => 'p.price', 'page' => 2]));
    }

    public function test_manufacturer_filter_with_other_conditions_is_not_redirected(): void
    {
        $this->get(route('category.show', ['category' => $this->batteries, 'manufacturer' => [$this->titan->id], 'available' => 1]))->assertOk();
        $this->get(route('category.show', ['category' => $this->batteries, 'manufacturer' => [$this->titan->id, 999]]))->assertOk();
    }

    public function test_manufacturer_filter_without_brand_page_is_not_redirected(): void
    {
        $varta = Manufacturer::query()->where('slug', 'varta')->firstOrFail();

        $this->get(route('category.show', ['category' => $this->batteries, 'manufacturer' => [$varta->id]]))->assertOk();
    }

    public function test_category_page_links_to_brand_pages(): void
    {
        $brandUrl = route('category.manufacturer', [$this->batteries, $this->titan]);

        $this->get(route('category.show', $this->batteries))
            ->assertOk()
            ->assertSee('href="'.$brandUrl.'"', false)
            ->assertDontSee(route('category.manufacturer', [$this->batteries, 'varta']));
    }

    public function test_menu_links_to_brand_page_when_it_exists_and_to_filter_otherwise(): void
    {
        $varta = Manufacturer::query()->where('slug', 'varta')->firstOrFail();

        $this->get(route('home'))
            ->assertSee('href="'.route('category.manufacturer', [$this->batteries, $this->titan]).'"', false)
            ->assertSee('href="'.route('category.show', ['category' => $this->batteries, 'manufacturer' => [$varta->id]]).'"', false);
    }

    public function test_several_manufacturers_are_noindex_with_canonical_on_the_category(): void
    {
        $varta = Manufacturer::query()->where('slug', 'varta')->firstOrFail();
        $categoryUrl = route('category.show', $this->batteries);

        $this->get(route('category.show', ['category' => $this->batteries, 'manufacturer' => [$this->titan->id, $varta->id], 'page' => 1]))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee('<link rel="canonical" href="'.$categoryUrl.'">', false);
    }

    public function test_attribute_selection_on_the_category_is_noindex_with_canonical_on_the_category(): void
    {
        $this->get(route('category.show', ['category' => $this->batteries, 'attr' => [13 => [42]]]))
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee('<link rel="canonical" href="'.route('category.show', $this->batteries).'">', false);
    }

    public function test_case_type_selection_is_noindex(): void
    {
        $this->get(route('category.show', ['category' => $this->batteries, 'case_type' => [3]]))
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_selection_on_top_of_a_brand_page_points_canonical_to_the_brand_page(): void
    {
        $brandUrl = route('category.manufacturer', [$this->batteries, $this->titan]);

        $this->get($brandUrl.'?attr[13][]=42&page=2')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee('<link rel="canonical" href="'.$brandUrl.'">', false);
    }

    public function test_single_manufacturer_with_a_page_plus_selection_points_canonical_to_the_brand_page(): void
    {
        $this->get(route('category.show', ['category' => $this->batteries, 'manufacturer' => [$this->titan->id], 'attr' => [13 => [42]]]))
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee('<link rel="canonical" href="'.route('category.manufacturer', [$this->batteries, $this->titan]).'">', false);
    }

    public function test_price_and_availability_filters_stay_indexable_with_canonical_on_the_category(): void
    {
        $this->get(route('category.show', ['category' => $this->batteries, 'price_from' => 1000, 'available' => 1]))
            ->assertOk()
            ->assertDontSee('noindex')
            ->assertSee('<link rel="canonical" href="'.route('category.show', $this->batteries).'">', false);
    }

    public function test_clean_category_and_brand_page_are_indexable(): void
    {
        $this->get(route('category.show', $this->batteries))->assertOk()->assertDontSee('noindex');
        $this->get(route('category.manufacturer', [$this->batteries, $this->titan]))->assertOk()->assertDontSee('noindex');
    }

    private function product(string $name, Manufacturer $manufacturer, Category $category): void
    {
        Product::query()
            ->create([
                'name' => $name,
                'slug' => str($name)->slug()->value(),
                'manufacturer_id' => $manufacturer->id,
                'price' => 7000,
                'status' => true,
            ])
            ->categories()
            ->attach($category);
    }
}
