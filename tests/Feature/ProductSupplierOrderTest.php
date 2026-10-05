<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductSupplierOrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{int, int, bool}>
     */
    public static function availability(): array
    {
        return [
            'нет у нас, у поставщика 5' => [0, 5, true],
            'нет у нас, у поставщика 4' => [0, 4, false],
            'есть у нас' => [2, 50, false],
        ];
    }

    #[DataProvider('availability')]
    public function test_on_order_threshold(int $quantity, int $supplierQuantity, bool $expected): void
    {
        $product = $this->makeProduct(['quantity' => $quantity, 'supplier_quantity' => $supplierQuantity]);

        $this->assertSame($expected, $product->isAvailableOnOrder());
    }

    public function test_threshold_is_configurable(): void
    {
        config(['shop.supplier_order_min_quantity' => 10]);

        $this->assertFalse($this->makeProduct(['supplier_quantity' => 5])->isAvailableOnOrder());
    }

    public function test_display_price_uses_supplier_price_only_on_order(): void
    {
        $this->assertSame(10900.0, $this->makeProduct(['supplier_quantity' => 5, 'supplier_price' => 10900])->displayPrice());
        $this->assertSame(10100.0, $this->makeProduct(['supplier_quantity' => 5, 'supplier_price' => null])->displayPrice());
        $this->assertSame(10100.0, $this->makeProduct(['quantity' => 1, 'supplier_quantity' => 5, 'supplier_price' => 10900])->displayPrice());
    }

    public function test_product_page_shows_order_button_and_supplier_price(): void
    {
        $product = $this->makeProduct(['supplier_quantity' => 8, 'supplier_price' => 10900]);

        $this->get(route('product.show', $product))
            ->assertOk()
            ->assertSee('Заказать')
            ->assertSee('10 900 р.')
            ->assertDontSee('В корзину');
    }

    public function test_admin_stock_filter_finds_on_order_products(): void
    {
        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]), 'moonshine');

        $this->makeProduct(['name' => 'Под заказ АКБ', 'slug' => 'on-order', 'supplier_quantity' => 6]);
        $this->makeProduct(['name' => 'Нет нигде АКБ', 'slug' => 'nowhere', 'supplier_quantity' => 1]);

        $table = fn (string $stock) => $this->get('/admin/component/product-index-page/product-resource?'.http_build_query([
            '_component_name' => 'index-table-product-resource',
            'filter' => ['stock' => $stock],
        ]))->assertOk();

        $table('order')->assertSee('Под заказ АКБ')->assertDontSee('Нет нигде АКБ')->assertSee('У поставщика');

        $supplierRange = $this->get('/admin/component/product-index-page/product-resource?'.http_build_query([
            '_component_name' => 'index-table-product-resource',
            'filter' => ['supplier_quantity' => ['from' => 5, 'to' => '']],
        ]))->assertOk();
        $supplierRange->assertSee('Под заказ АКБ')->assertDontSee('Нет нигде АКБ');

        $filters = $this->get('/admin/resource/product-resource/product-index-page')->assertOk()->getContent();
        $this->assertMatchesRegularExpression("/\\['range_from_filter_supplier_quantity'\\]:\\s*'',\\s*\\['range_to_filter_supplier_quantity'\\]:\\s*'',/", $filters);
        $this->assertStringContainsString('name="filter[supplier_price][from]"', $filters);
        $table('out')->assertSee('Нет нигде АКБ')->assertDontSee('Под заказ АКБ');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeProduct(array $attributes = []): Product
    {
        return Product::query()->create([
            'name' => 'TITAN EFB 70 Ah О.П.',
            'slug' => 'titan-efb-70-'.uniqid(),
            'price' => 10100,
            'quantity' => 0,
            'status' => true,
            ...$attributes,
        ]);
    }
}
