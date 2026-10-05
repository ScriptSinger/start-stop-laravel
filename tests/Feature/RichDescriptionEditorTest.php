<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class RichDescriptionEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($admin, 'moonshine');
    }

    public function test_product_category_and_page_forms_use_rich_editor(): void
    {
        $product = Product::query()->create(['name' => 'TITAN', 'slug' => 'titan', 'price' => 1, 'description' => '<p>Описание <b>АКБ</b></p>']);
        $category = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori']);
        $page = Page::query()->create(['title' => 'О компании', 'slug' => 'o-kompanii']);

        foreach ([
            "/admin/resource/product-resource/product-form-page/{$product->id}",
            "/admin/resource/category-resource/category-form-page/{$category->id}",
            "/admin/resource/page-resource/page-form-page/{$page->id}",
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('vendor/moonshine-tinymce/tinymce.min.js', false);
        }
    }

    public function test_html_description_is_saved_as_is(): void
    {
        $product = Product::query()->create(['name' => 'TITAN', 'slug' => 'titan', 'price' => 1]);
        $html = '<p>Пусковой ток <strong>540 A</strong></p><table><tr><td>12V</td></tr></table>';

        $this->put("/admin/resource/product-resource/crud/{$product->id}", [
            'name' => 'TITAN',
            'slug' => 'titan',
            'price' => 1,
            'quantity' => 0,
            'status' => 1,
            'description' => $html,
        ])->assertRedirect();

        $this->assertSame($html, $product->refresh()->description);
    }
}
