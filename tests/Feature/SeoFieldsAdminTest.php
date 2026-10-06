<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class SeoFieldsAdminTest extends TestCase
{
    use RefreshDatabase;

    private const SEO = [
        'heading' => 'Аккумулятор ZUBR 60 Ah',
        'meta_title' => 'Купить ZUBR 60 Ah в Уфе | Старт-Стоп',
        'meta_description' => 'ZUBR 60 Ah с доставкой по Уфе.',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]), 'moonshine');
    }

    public function test_product_form_shows_and_saves_seo_fields(): void
    {
        $product = Product::query()->create(['name' => 'ZUBR 60', 'slug' => 'zubr-60', 'price' => 7600, 'meta_title' => 'Старый title']);

        $this->get("/admin/resource/product-resource/product-form-page/{$product->id}")
            ->assertOk()
            ->assertSee('name="meta_title"', false)
            ->assertSee('Старый title');

        $this->put("/admin/resource/product-resource/crud/{$product->id}", [
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => 7600,
            'quantity' => 0,
            'status' => 1,
            ...self::SEO,
        ])->assertRedirect();

        $this->assertSame(self::SEO, $product->refresh()->only(array_keys(self::SEO)));
    }

    public function test_category_form_saves_seo_fields(): void
    {
        $category = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori']);

        $this->put("/admin/resource/category-resource/crud/{$category->id}", [
            'name' => $category->name,
            'slug' => $category->slug,
            'sort_order' => 0,
            'status' => 1,
            ...self::SEO,
        ])->assertRedirect();

        $this->assertSame(self::SEO, $category->refresh()->only(array_keys(self::SEO)));
    }

    public function test_page_form_saves_seo_fields(): void
    {
        $page = Page::query()->create(['title' => 'Доставка', 'slug' => 'dostavka', 'status' => true]);

        $this->put("/admin/resource/page-resource/crud/{$page->id}", [
            'title' => $page->title,
            'slug' => $page->slug,
            'sort_order' => 0,
            'status' => 1,
            ...self::SEO,
        ])->assertRedirect();

        $this->assertSame(self::SEO, $page->refresh()->only(array_keys(self::SEO)));
    }

    public function test_too_long_meta_description_is_rejected(): void
    {
        $category = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori']);

        $this->put("/admin/resource/category-resource/crud/{$category->id}", [
            'name' => $category->name,
            'slug' => $category->slug,
            'meta_description' => str_repeat('а', 501),
        ])->assertSessionHasErrorsIn('category-resource', 'meta_description');

        $this->assertNull($category->refresh()->meta_description);
    }
}
