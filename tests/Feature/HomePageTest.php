<?php

namespace Tests\Feature;

use App\Enums\BannerPosition;
use App\Enums\ProductSelection;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_only_active_banners_with_links(): void
    {
        Banner::query()->create(['position' => BannerPosition::HomeSlider, 'image' => 'catalog/banners/trade-in.png', 'url' => '/page/trade-in']);
        Banner::query()->create(['position' => BannerPosition::HomeSlider, 'image' => 'catalog/banners/old.png', 'is_active' => false]);
        Banner::query()->create(['position' => BannerPosition::HomeStrip, 'image' => 'catalog/banners/delivery.png']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('catalog/banners/trade-in.png', false)
            ->assertSee('href="'.url('/page/trade-in').'"', false)
            ->assertSee('catalog/banners/delivery.png', false)
            ->assertDontSee('catalog/banners/old.png', false);
    }

    public function test_jivo_chat_widget_is_configurable(): void
    {
        config(['shop.jivo_widget_id' => 'abc123']);
        $this->get('/')->assertSee('<meta name="jivo-widget" content="abc123" />', false);

        config(['shop.jivo_widget_id' => null]);
        $this->get('/')->assertDontSee('jivo-widget');
    }

    public function test_category_wall_links_subcategories_and_manufacturers(): void
    {
        $batteries = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori', 'home_wall_sort' => 0]);
        $oils = Category::query()->create(['name' => 'Автомасла', 'slug' => 'avtomasla', 'home_wall_sort' => 1]);
        Category::query()->create(['name' => 'Моторное масло', 'slug' => 'motornoe', 'parent_id' => $oils->id, 'show_on_parent_wall' => true]);
        Category::query()->create(['name' => 'Скрытая подкатегория', 'slug' => 'hidden', 'parent_id' => $oils->id]);
        Category::query()->create(['name' => 'Лампы', 'slug' => 'lampy']);

        $titan = Manufacturer::query()->create(['name' => 'TITAN', 'slug' => 'titan']);
        $batteries->wallManufacturers()->attach($titan, ['sort_order' => 0]);

        $response = $this->get(route('home'))->assertOk()->assertSee('Популярные категории');
        $wall = substr($response->getContent(), strpos($response->getContent(), 'category-wall_v2-0'));

        $this->assertStringContainsString('Моторное масло', $wall);
        $this->assertStringContainsString(e(route('category.show', ['category' => $batteries, 'manufacturer' => [$titan->id]])), $wall);
        $this->assertStringNotContainsString('Скрытая подкатегория', $wall);
        $this->assertStringNotContainsString('>Лампы<', $wall);
    }

    public function test_selections_show_active_products_with_card_details(): void
    {
        $promo = $this->product('ТЮМЕНЬ ASIA 40 Ah', ['price' => 5400, 'special_price' => 4900, 'trade_in_discount' => 500, 'is_pickup_only' => true]);
        $archived = $this->product('Архивный АКБ', ['status' => false]);
        DB::table('product_selection')->insert([
            ['selection' => ProductSelection::Promo->value, 'product_id' => $promo->id, 'sort_order' => 0],
            ['selection' => ProductSelection::Promo->value, 'product_id' => $archived->id, 'sort_order' => 1],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Акции')
            ->assertSee('ТЮМЕНЬ ASIA 40 Ah')
            ->assertDontSee('Архивный АКБ')
            ->assertSee('Ваша скидка: 500р.')
            ->assertSee('Трейд-ин 500 руб.')
            ->assertSee('Только самовывоз')
            ->assertSee('<span class="price-old" x-text="format(shownPrice)">5400р.</span> <span class="price-new" x-text="format(shownSpecial)">4900р.</span>', false)
            // Галочка трейд-ина — часть формы «В корзину» этой карточки; цена 4900 − 500.
            ->assertSee('form="add-to-cart-'.$promo->id.'"', false)
            ->assertSee('4400р.');
    }

    public function test_special_price_is_charged_in_cart(): void
    {
        $product = $this->product('ТЮМЕНЬ ASIA 40 Ah', ['price' => 5400, 'special_price' => 4900, 'trade_in_discount' => 500]);

        $this->post(route('cart.store', $product), ['trade_in' => 1]);

        $this->get(route('cart.index'))->assertSee('4400р.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $name, array $attributes = []): Product
    {
        return Product::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'price' => 7000,
            'quantity' => 1,
            'status' => true,
            ...$attributes,
        ]);
    }
}
