<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoMarkupTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_and_contacts_describe_the_shop_as_local_business(): void
    {
        config(['shop.opening_hours' => [['days' => 'Пн–Пт', 'schema_days' => 'Mo-Fr', 'from' => '09:00', 'to' => '18:00']]]);

        foreach (['home', 'contact'] as $route) {
            $shop = $this->structuredData(route($route))[0];

            $this->assertSame('AutoPartsStore', $shop['@type']);
            $this->assertSame('Старт-Стоп', $shop['name']);
            $this->assertSame('+7 (987) 250-00-26', $shop['telephone']);
            $this->assertSame('Уфа', $shop['address']['addressLocality']);
            $this->assertSame('бульвар Тухвата Янаби, 36', $shop['address']['streetAddress']);
            $this->assertSame(config('shop.map_coordinates')[0], $shop['geo']['latitude']);
            $this->assertSame(['Mo-Fr 09:00-18:00'], $shop['openingHours']);
            $this->assertSame(['https://vk.com/startstop102'], $shop['sameAs']);
        }
    }

    public function test_opening_hours_are_shown_on_contacts_and_footer_and_in_markup(): void
    {
        $this->get(route('contact'))->assertSee('Режим работы')->assertSee('Ежедневно: 10:00–20:00');
        $this->get(route('home'))->assertSee('Ежедневно: 10:00–20:00');
        $this->assertSame(['Mo-Su 10:00-20:00'], $this->structuredData(route('home'))[0]['openingHours']);
    }

    public function test_local_business_skips_opening_hours_when_they_are_unknown(): void
    {
        config(['shop.opening_hours' => []]);

        $this->assertArrayNotHasKey('openingHours', $this->structuredData(route('home'))[0]);
    }

    public function test_product_data_has_images_description_and_offer_details(): void
    {
        $product = $this->product('ZUBR 60 Ah', ['image' => 'catalog/zubr.jpg']);
        ProductImage::query()->create(['product_id' => $product->id, 'path' => 'catalog/zubr-2.jpg', 'sort_order' => 1]);

        $data = $this->structuredData(route('product.show', $product))[0];

        $this->assertSame([url('/storage/catalog/zubr.jpg'), url('/storage/catalog/zubr-2.jpg')], $data['image']);
        $this->assertStringStartsWith('ZUBR 60 Ah — купить в Уфе за 7 000 ₽.', $data['description']);
        $this->assertSame('https://schema.org/NewCondition', $data['offers']['itemCondition']);
        $this->assertSame(now()->addMonth()->toDateString(), $data['offers']['priceValidUntil']);
    }

    public function test_open_graph_has_locale_site_name_and_default_image(): void
    {
        $this->get(route('home'))
            ->assertSee('<meta property="og:locale" content="ru_RU">', false)
            ->assertSee('<meta property="og:site_name" content="Старт-Стоп">', false)
            ->assertSee('<meta property="og:image" content="'.url('/storage/'.config('shop.logo')).'">', false);
    }

    public function test_product_open_graph_has_price(): void
    {
        $this->get(route('product.show', $this->product('ZUBR 60 Ah')))
            ->assertSee('<meta property="product:price:amount" content="7000.00">', false)
            ->assertSee('<meta property="product:price:currency" content="RUB">', false);
    }

    public function test_product_without_photo_has_no_image_in_data(): void
    {
        $data = $this->structuredData(route('product.show', $this->product('ZUBR 60 Ah')))[0];

        $this->assertArrayNotHasKey('image', $data);
    }

    public function test_product_meta_description_is_generated_only_when_empty(): void
    {
        $this->get(route('product.show', $this->product('ZUBR 60 Ah', slug: 'zubr')))
            ->assertSee('<meta name="description" content="ZUBR 60 Ah — купить в Уфе за 7 000 ₽. Доставка по городу, гарантия, трейд-ин старого аккумулятора.">', false);

        $this->get(route('product.show', $this->product('TITAN 60', ['meta_description' => 'Свой текст'], 'titan')))
            ->assertSee('<meta name="description" content="Свой текст">', false);
    }

    public function test_category_has_fallback_description_breadcrumbs_and_page_number_in_title(): void
    {
        $parent = Category::query()->create(['name' => 'Автохимия', 'slug' => 'avtohimiya']);
        $category = Category::query()->create(['name' => 'Антифриз', 'slug' => 'antifriz', 'parent_id' => $parent->id]);

        foreach (range(1, 25) as $number) {
            $this->product("Антифриз {$number}", slug: "antifriz-{$number}")->categories()->attach($category);
        }

        $first = $this->get(route('category.show', $category))
            ->assertSee('<title>Антифриз — Старт-Стоп</title>', false)
            ->assertSee('<meta name="description" content="Антифриз — купить в Уфе в магазине Старт-Стоп. Цены, характеристики, наличие. Доставка по городу, гарантия.">', false);

        $this->assertSame(['Старт-Стоп', 'Автохимия', 'Антифриз'], $this->breadcrumbNames($first->getContent()));

        $this->get(route('category.show', ['category' => $category, 'page' => 2]))
            ->assertSee('<title>Антифриз — страница 2 — Старт-Стоп</title>', false);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function structuredData(string $url): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $this->get($url)->assertOk()->getContent(), $matches);

        return array_map(fn (string $json): array => json_decode($json, true), $matches[1]);
    }

    /**
     * @return list<string>
     */
    private function breadcrumbNames(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        $breadcrumbs = collect($matches[1])
            ->map(fn (string $json): array => json_decode($json, true))
            ->firstWhere('@type', 'BreadcrumbList');

        return array_column($breadcrumbs['itemListElement'], 'name');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $name, array $attributes = [], string $slug = 'p-1'): Product
    {
        return Product::query()->create([
            'name' => $name,
            'slug' => $slug,
            'price' => 7000,
            'quantity' => 2,
            'status' => true,
            ...$attributes,
        ]);
    }
}
