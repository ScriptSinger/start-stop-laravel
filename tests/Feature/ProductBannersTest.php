<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBannersTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_page_shows_advantages_with_title_and_note(): void
    {
        $product = Product::query()->create(['name' => 'AKOM 60 Ah', 'slug' => 'akom-60-ah', 'price' => 8300, 'status' => true]);

        $this->get(route('product.show', $product))
            ->assertOk()
            ->assertSee('<strong class="product-banner__title">Бесплатная доставка</strong>', false)
            ->assertSee('<span class="product-banner__note">по Уфе, в основных районах</span>', false)
            ->assertSee('Оплата при получении')
            ->assertSee('100% гарантия на брак')
            ->assertDontSee('Скидки и бонусы');
    }

    public function test_advantage_without_note_has_no_empty_note_element(): void
    {
        config(['shop.product_banners' => [['icon' => 'fas fa-wallet', 'title' => 'Оплата при получении', 'text' => null]]]);
        $product = Product::query()->create(['name' => 'AKOM 60 Ah', 'slug' => 'akom-60-ah', 'price' => 8300, 'status' => true]);

        $this->get(route('product.show', $product))
            ->assertOk()
            ->assertSee('Оплата при получении')
            ->assertDontSee('product-banner__note', false);
    }
}
