<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Manufacturer;
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
            ->assertSee('10 900 ₽')
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

    public function test_admin_index_shows_storefront_price_for_on_order_products(): void
    {
        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]), 'moonshine');

        $this->makeProduct(['name' => 'Под заказ АКБ', 'slug' => 'on-order', 'price' => 0, 'supplier_quantity' => 6, 'supplier_price' => 12350]);

        $this->get('/admin/component/product-index-page/product-resource?'.http_build_query([
            '_component_name' => 'index-table-product-resource',
        ]))->assertOk()->assertSee('Цена на сайте')->assertSee('12 350 ₽');
    }

    public function test_admin_index_shows_special_price_and_sorts_by_it_with_regular_products_last(): void
    {
        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]), 'moonshine');

        $this->makeProduct(['name' => 'Без акции', 'slug' => 'regular', 'price' => 5000, 'quantity' => 3]);
        $this->makeProduct(['name' => 'Акция дорогая', 'slug' => 'special-high', 'price' => 9000, 'quantity' => 3, 'special_price' => 8100]);
        $this->makeProduct(['name' => 'Акция дешёвая', 'slug' => 'special-low', 'price' => 4000, 'quantity' => 3, 'special_price' => 3300]);

        $url = '/admin/component/product-index-page/product-resource?_component_name=index-table-product-resource';

        $this->get($url.'&sort=special_price')
            ->assertOk()
            ->assertSee('Цена по акции')
            ->assertSeeInOrder(['Акция дешёвая', 'Акция дорогая', 'Без акции']);
        $this->get($url.'&sort=-special_price')
            ->assertSeeInOrder(['Акция дорогая', 'Акция дешёвая', 'Без акции']);
    }

    public function test_admin_index_flags_special_price_that_is_not_below_the_regular_price(): void
    {
        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]), 'moonshine');

        $this->makeProduct(['name' => 'Акция выше цены', 'slug' => 'above', 'price' => 5900, 'quantity' => 3, 'special_price' => 6900]);

        $url = '/admin/component/product-index-page/product-resource?_component_name=index-table-product-resource';

        $html = $this->get($url)->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'Акция не ниже обычной цены: на сайте не действует'));

        $this->makeProduct(['name' => 'Акция равна цене', 'slug' => 'equal', 'price' => 6900, 'quantity' => 3, 'special_price' => 6900]);
        $this->makeProduct(['name' => 'Нормальная акция', 'slug' => 'fine', 'price' => 5000, 'quantity' => 3, 'special_price' => 4500]);
        $this->makeProduct(['name' => 'Без акции', 'slug' => 'none', 'price' => 5000, 'quantity' => 3]);

        $html = $this->get($url)->getContent();
        $this->assertSame(2, substr_count($html, 'Акция не ниже обычной цены: на сайте не действует'));
    }

    public function test_price_problem_ignores_on_order_products_with_supplier_price(): void
    {
        $this->makeProduct(['name' => 'Под заказ с ценой', 'slug' => 'on-order-priced', 'price' => 0, 'supplier_quantity' => 6, 'supplier_price' => 12350]);
        $this->makeProduct(['name' => 'Под заказ без цены', 'slug' => 'on-order-free', 'price' => 0, 'supplier_quantity' => 6, 'supplier_price' => null]);
        $this->makeProduct(['name' => 'На складе без цены', 'slug' => 'stock-free', 'price' => 0, 'quantity' => 3]);
        $this->makeProduct(['name' => 'Акция выше цены', 'slug' => 'bad-special', 'price' => 1000, 'quantity' => 3, 'special_price' => 1200]);
        $this->makeProduct(['name' => 'Всё в порядке', 'slug' => 'fine', 'price' => 1000, 'quantity' => 3]);

        $this->assertEqualsCanonicalizing(
            ['Под заказ без цены', 'На складе без цены', 'Акция выше цены'],
            Product::query()->withPriceProblem()->pluck('name')->all(),
        );
    }

    public function test_ready_for_export_accepts_on_order_products_with_supplier_price(): void
    {
        $manufacturer = Manufacturer::query()->create(['name' => 'ZUBR', 'slug' => 'zubr']);
        $category = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akb']);
        $ready = ['image' => 'catalog/products/a.jpg', 'manufacturer_id' => $manufacturer->getKey()];

        $onOrder = $this->makeProduct($ready + ['name' => 'Под заказ с ценой', 'slug' => 'export-on-order', 'price' => 0, 'supplier_quantity' => 6, 'supplier_price' => 12350]);
        $noPrice = $this->makeProduct($ready + ['name' => 'Под заказ без цены', 'slug' => 'export-free', 'price' => 0, 'supplier_quantity' => 6, 'supplier_price' => null]);
        $onOrder->categories()->attach($category);
        $noPrice->categories()->attach($category);

        $this->assertSame(['Под заказ с ценой'], Product::query()->readyForExport()->pluck('name')->all());
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
