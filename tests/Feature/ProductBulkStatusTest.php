<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class ProductBulkStatusTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_product_list_shows_status_actions_and_hides_them_in_trash(): void
    {
        $this->product('active', true);
        $deleted = $this->product('deleted', true);
        $deleted->delete();
        $table = '/admin/component/product-index-page/product-resource?_component_name=index-table-product-resource';

        $this->get($table)->assertOk()->assertSee('Активировать')->assertSee('Деактивировать');
        $this->get($table.'&filter[deleted]=trashed')->assertOk()->assertDontSee('Активировать')->assertDontSee('Деактивировать');
    }

    public function test_sidebar_status_filter_shows_only_matching_products(): void
    {
        $this->product('visible-active-product', true);
        $this->product('visible-inactive-product', false);
        $deleted = $this->product('deleted-active-product', true);
        $deleted->delete();
        $table = '/admin/component/product-index-page/product-resource?_component_name=index-table-product-resource';

        $this->get($table.'&filter[status]=1')->assertOk()
            ->assertSee('visible-active-product')->assertDontSee('visible-inactive-product')->assertDontSee('deleted-active-product');
        $this->get($table.'&filter[status]=0')->assertOk()
            ->assertSee('visible-inactive-product')->assertDontSee('visible-active-product')->assertDontSee('deleted-active-product');
        $this->get($table)->assertOk()
            ->assertSee('visible-active-product')->assertSee('visible-inactive-product')->assertDontSee('deleted-active-product');
    }

    public function test_bulk_status_changes_only_selected_non_deleted_products(): void
    {
        $first = $this->product('first', false);
        $second = $this->product('second', false);
        $untouched = $this->product('untouched', false);
        $deleted = $this->product('deleted', false);
        $deleted->delete();
        $url = '/admin/method/product-index-page/product-resource?method=';

        $this->postJson($url.'activateProducts', ['ids' => [$first->id, $second->id, $deleted->id, $first->id]])
            ->assertOk()->assertJsonPath('message', 'Включено товаров: 2');

        $this->assertTrue($first->fresh()->status);
        $this->assertTrue($second->fresh()->status);
        $this->assertFalse($untouched->fresh()->status);
        $this->assertFalse($deleted->fresh()->status);
        $this->get(route('product.show', $first))->assertOk();

        $this->postJson($url.'activateProducts', ['ids' => [$first->id, $second->id]])
            ->assertOk()->assertJsonPath('message', 'Включено товаров: 0');

        $this->postJson($url.'deactivateProducts', ['ids' => [$first->id]])
            ->assertOk()->assertJsonPath('message', 'Отключено товаров: 1');

        $this->assertFalse($first->fresh()->status);
        $this->assertTrue($second->fresh()->status);
        $this->get(route('product.show', $first->slug))->assertNotFound();
    }

    public function test_status_action_requires_a_valid_non_empty_selection(): void
    {
        $product = $this->product('first', false);
        $url = '/admin/method/product-index-page/product-resource?method=activateProducts';

        $this->postJson($url, ['ids' => []])->assertOk()->assertJsonPath('message', 'Отметьте товары в списке.');
        $this->postJson($url, ['ids' => [$product->id, 'invalid']])->assertUnprocessable()->assertJsonValidationErrors('ids.1');
        $this->assertFalse($product->fresh()->status);
    }

    public function test_guest_cannot_change_product_status(): void
    {
        $product = $this->product('first', false);
        auth('moonshine')->logout();

        $this->postJson('/admin/method/product-index-page/product-resource?method=activateProducts', ['ids' => [$product->id]])
            ->assertUnauthorized();

        $this->assertFalse($product->fresh()->status);
    }

    private function product(string $name, bool $status): Product
    {
        return Product::query()->create(['name' => $name, 'slug' => $name, 'price' => 1000, 'status' => $status]);
    }
}
