<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\StockSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class ProductSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    private Product $active;

    private Product $deleted;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]), 'moonshine');

        $category = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori']);

        $this->active = $this->product('ZUBR 60Ah О.П.', $category);
        $this->deleted = $this->product('Дубль DELKOR 60Ah О.П.', $category);
        $this->deleted->delete();
    }

    public function test_deleting_keeps_row_and_hides_product_from_storefront(): void
    {
        $this->assertSoftDeleted($this->deleted);
        $this->assertSame(1, Product::query()->count());
        $this->assertSame(2, Product::withTrashed()->count());

        $this->get(route('product.show', $this->active))->assertOk();
        $this->get(route('product.show', $this->deleted->slug))->assertNotFound();
    }

    public function test_deleted_product_is_not_counted_in_stock_summary(): void
    {
        $summary = app(StockSummary::class);

        $this->assertSame(1, $summary->overall()['total']);
        $this->assertSame(1, $summary->byCategory()->sum('total'));
        $this->assertSame(1, $summary->byManufacturer()->sum('total'));
    }

    public function test_deleted_products_are_listed_only_on_the_trashed_tab(): void
    {
        $this->productTable([])
            ->assertSee('ZUBR 60Ah О.П.')
            ->assertDontSee('Дубль DELKOR 60Ah О.П.');

        $this->productTable(['query-tag' => 'trashed'])
            ->assertSee('Дубль DELKOR 60Ah О.П.')
            ->assertDontSee('ZUBR 60Ah О.П.')
            ->assertSee('Восстановить')
            ->assertSee($this->deleted->deleted_at->format('d.m.Y H:i'));
    }

    public function test_deleted_product_has_no_edit_or_delete_buttons_but_active_has(): void
    {
        $this->productTable([])
            ->assertSee("product-resource-delete-modal-{$this->active->id}", false)
            ->assertSee("product-form-page/{$this->active->id}", false);

        $this->productTable(['query-tag' => 'trashed'])
            ->assertDontSee("product-resource-delete-modal-{$this->deleted->id}", false)
            ->assertDontSee("product-form-page/{$this->deleted->id}", false)
            ->assertSee("product-detail-page/{$this->deleted->id}", false);
    }

    public function test_trashed_tab_exists_on_index_page(): void
    {
        $this->get('/admin/resource/product-resource/product-index-page')
            ->assertOk()
            ->assertSee('Удалённые');
    }

    public function test_deleted_product_detail_page_opens_with_deletion_date(): void
    {
        $this->get("/admin/resource/product-resource/product-detail-page/{$this->deleted->id}")
            ->assertOk()
            ->assertSee('Дубль DELKOR 60Ah О.П.')
            ->assertSee('Удалён');
    }

    public function test_restore_by_selected_ids(): void
    {
        $second = $this->product('Дубль MUTLU 60Ah О.П.');
        $second->delete();

        $this->postJson('/admin/method/product-index-page/product-resource?method=restore', ['ids' => [$this->deleted->id, $second->id]])
            ->assertOk()
            ->assertJsonPath('message', 'Восстановлено товаров: 2');

        $this->assertNotSoftDeleted($this->deleted);
        $this->assertNotSoftDeleted($second);
    }

    public function test_restore_single_product_from_row_button(): void
    {
        $this->postJson("/admin/method/product-index-page/product-resource?method=restore&resourceItem={$this->deleted->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Восстановлено товаров: 1');

        $this->assertNotSoftDeleted($this->deleted);
        $this->get(route('product.show', $this->deleted))->assertOk();
    }

    public function test_restore_does_not_touch_active_products(): void
    {
        $this->postJson('/admin/method/product-index-page/product-resource?method=restore', ['ids' => [$this->active->id]])
            ->assertOk()
            ->assertJsonPath('message', 'Нечего восстанавливать');

        $this->assertNotSoftDeleted($this->active);
    }

    private function productTable(array $query): TestResponse
    {
        return $this->get('/admin/component/product-index-page/product-resource?'.http_build_query([
            '_component_name' => 'index-table-product-resource',
            ...$query,
        ]))->assertOk();
    }

    private function product(string $name, ?Category $category = null): Product
    {
        $product = Product::query()->create(['name' => $name, 'slug' => str($name)->slug()->value(), 'price' => 7000, 'quantity' => 2, 'status' => true]);
        $category?->products()->attach($product);

        return $product;
    }
}
