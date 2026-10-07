<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CategoryTreeTest extends TestCase
{
    use RefreshDatabase;

    private Category $oils;

    private Category $motorOil;

    private Category $filters;

    private Category $hidden;

    protected function setUp(): void
    {
        parent::setUp();

        $this->oils = Category::query()->create(['name' => 'Автомасла', 'slug' => 'avtomasla']);
        $this->motorOil = Category::query()->create(['name' => 'Моторное масло', 'slug' => 'motornoe', 'parent_id' => $this->oils->id]);
        $this->filters = Category::query()->create(['name' => 'Фильтры масляные', 'slug' => 'filtry', 'parent_id' => $this->oils->id]);
        $this->hidden = Category::query()->create(['name' => 'Старое', 'slug' => 'staroe', 'parent_id' => $this->oils->id, 'status' => false]);
    }

    public function test_section_shows_products_of_its_subsections(): void
    {
        $this->product('Лукойл 5W-40', [$this->motorOil]);
        $this->product('Фильтр Madfil', [$this->filters]);
        $this->product('Из выключенного подраздела', [$this->hidden]);

        $this->get(route('category.show', $this->oils))
            ->assertOk()
            ->assertSee('Лукойл 5W-40')
            ->assertSee('Фильтр Madfil')
            ->assertDontSee('Из выключенного подраздела');

        $this->get(route('category.show', $this->filters))
            ->assertOk()
            ->assertSee('Фильтр Madfil')
            ->assertDontSee('Лукойл 5W-40');

        $this->getJson(route('category.count', $this->oils))->assertJson(['count' => 2]);
        $this->get(route('search', ['search' => 'Фильтр', 'category_id' => $this->oils->id]))->assertSee('Фильтр Madfil');
    }

    public function test_cleanup_removes_parent_links_and_promo_category(): void
    {
        $promo = Category::query()->create(['name' => 'Акции', 'slug' => 'aktsii', 'status' => false]);
        $oil = $this->product('Лукойл 5W-40', [$this->oils, $this->motorOil, $promo]);
        // Подраздел выключен — привязка к разделу не лишняя, иначе товар пропадёт из раздела.
        $old = $this->product('Из выключенного подраздела', [$this->oils, $this->hidden]);

        $this->artisan('catalog:cleanup-categories')
            ->expectsOutputToContain('Пробный прогон: база не изменена.')
            ->assertSuccessful();
        $this->assertSame(3, $oil->categories()->count());

        $this->artisan('catalog:cleanup-categories', ['--force' => true])->assertSuccessful();

        $this->assertSame([$this->motorOil->id], $oil->categories()->pluck('categories.id')->all());
        $this->assertEqualsCanonicalizing([$this->oils->id, $this->hidden->id], $old->categories()->pluck('categories.id')->all());
        $this->assertModelMissing($promo);
    }

    public function test_promo_category_stays_when_it_is_the_only_category_of_a_product(): void
    {
        $promo = Category::query()->create(['name' => 'Акции', 'slug' => 'aktsii', 'status' => false]);
        $this->product('Только в акциях', [$promo]);

        $this->artisan('catalog:cleanup-categories', ['--force' => true])
            ->expectsOutputToContain('Категория «Акции» оставлена')
            ->assertSuccessful();

        $this->assertModelExists($promo);
        $this->assertSame(1, DB::table('category_product')->where('category_id', $promo->id)->count());
    }

    /**
     * @param  list<Category>  $categories
     */
    private function product(string $name, array $categories): Product
    {
        $product = Product::query()->create(['name' => $name, 'slug' => str($name)->slug()->value(), 'price' => 1000, 'quantity' => 1, 'status' => true]);
        $product->categories()->attach(array_map(fn (Category $category): int => $category->id, $categories));

        return $product;
    }
}
