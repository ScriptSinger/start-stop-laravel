<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchPageTest extends TestCase
{
    use RefreshDatabase;

    private Category $batteries;

    private Category $trucks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->batteries = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori', 'status' => true]);
        $this->trucks = Category::query()->create(['name' => 'Грузовые аккумуляторы', 'slug' => 'gruzovie', 'status' => true]);
    }

    public function test_every_word_must_be_in_name_or_query_matches_code(): void
    {
        $this->product('TITAN EFB 70 Ah', $this->batteries, ['code' => 'T-70']);
        $this->product('TITAN ASIA 80 Ah', $this->batteries);
        $this->product('Архивный TITAN EFB', $this->batteries, ['status' => false]);

        $this->get(route('search', ['search' => 'efb titan']))
            ->assertOk()
            ->assertSee('<h1>Поиск - efb titan</h1>', false)
            ->assertSee('TITAN EFB 70 Ah')
            ->assertDontSee('TITAN ASIA 80 Ah')
            ->assertDontSee('Архивный TITAN EFB');

        $this->get(route('search', ['search' => 'T-70']))->assertSee('TITAN EFB 70 Ah');
    }

    public function test_like_wildcards_are_plain_characters(): void
    {
        $this->product('TITAN 70 Ah', $this->batteries);

        $this->get(route('search', ['search' => '%']))
            ->assertSee('Нет товаров, которые соответствуют критериям поиска.')
            ->assertDontSee('TITAN 70 Ah');
    }

    public function test_category_select_narrows_results(): void
    {
        $this->product('TITAN 70 Ah', $this->batteries);
        $this->product('TITAN 190 Ah', $this->trucks);

        $this->get(route('search', ['search' => 'TITAN', 'category_id' => $this->trucks->id]))
            ->assertSee('TITAN 190 Ah')
            ->assertDontSee('TITAN 70 Ah')
            ->assertSee('<option value="'.$this->trucks->id.'" selected>', false);
    }

    public function test_brand_tiles_and_found_in_categories(): void
    {
        $titan = Manufacturer::query()->create(['name' => 'TITAN', 'slug' => 'titan']);
        $this->product('TITAN 70 Ah', $this->batteries, ['manufacturer_id' => $titan->id]);
        $this->product('TITAN 190 Ah', $this->trucks, ['manufacturer_id' => $titan->id]);

        $this->get(route('search', ['search' => 'titan']))
            ->assertSeeInOrder(['Категории', 'Товары', 'Найдено в категориях'])
            ->assertSee(e(route('category.show', ['category' => $this->batteries, 'manufacturer' => [$titan->id]])), false)
            ->assertSee(e(route('category.show', ['category' => $this->trucks, 'manufacturer' => [$titan->id]])), false)
            ->assertSee(e(route('search', ['search' => 'titan', 'category_id' => $this->trucks->id])), false);
    }

    public function test_empty_query_shows_prompt(): void
    {
        $this->product('TITAN 70 Ah', $this->batteries);

        $this->get(route('search'))
            ->assertOk()
            ->assertSee('Введите запрос в строку поиска.')
            ->assertDontSee('TITAN 70 Ah');
    }

    public function test_reviews_and_map_only_on_configured_info_pages(): void
    {
        $about = Page::query()->create(['title' => 'О компании', 'slug' => 'about_us', 'description' => '<p>Текст</p>', 'status' => true]);
        $terms = Page::query()->create(['title' => 'Условия', 'slug' => 'terms', 'description' => '<p>Текст</p>', 'status' => true]);

        $this->get(route('page.show', $about))->assertOk()->assertSee('myReviews__block-widget', false);
        $this->get(route('page.show', $terms))->assertOk()->assertDontSee('myReviews__block-widget', false);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $name, Category $category, array $attributes = []): Product
    {
        $product = Product::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'price' => 7000,
            'quantity' => 2,
            'status' => true,
            ...$attributes,
        ]);
        $product->categories()->attach($category);

        return $product;
    }
}
