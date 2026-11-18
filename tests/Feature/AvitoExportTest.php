<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Rap2hpoutre\FastExcel\FastExcel;
use Tests\TestCase;

class AvitoExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]), 'moonshine');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportedRows(array $query = []): array
    {
        $response = $this->get('/admin/resource/product-resource/handler/avito-export-handler?'.http_build_query($query));

        $response->assertOk();

        return (new FastExcel)->import($response->baseResponse->getFile()->getPathname())->all();
    }

    public function test_button_is_shown_on_product_list(): void
    {
        $this->get('/admin/resource/product-resource/product-index-page')
            ->assertOk()
            ->assertSee('Экспорт в Авито')
            ->assertSee('Экспорт в Excel');
    }

    public function test_export_has_requested_columns_in_order(): void
    {
        Product::query()->create(['name' => 'ZUBR 60Ah', 'slug' => 'zubr-60', 'price' => 7600, 'quantity' => 2]);

        $this->assertSame(
            ['Путь к изображению (URL)', 'Артикул', 'Код 1С', 'ID товара на сайте', 'Цена', 'Скидка за старый', 'Наименование', 'Цена на сайте', 'Кол-во', 'Количество у поставщика', 'Категория', 'Подкатегория', 'Производитель'],
            array_keys($this->exportedRows()[0]),
        );
    }

    public function test_only_in_stock_and_on_order_products_are_exported_ignoring_filters(): void
    {
        Product::query()->create(['name' => 'В наличии', 'slug' => 'in-stock', 'price' => 100, 'quantity' => 3, 'status' => false]);
        Product::query()->create(['name' => 'Под заказ', 'slug' => 'on-order', 'price' => 100, 'quantity' => 0, 'supplier_quantity' => 5, 'supplier_price' => 90]);
        Product::query()->create(['name' => 'Нет в наличии', 'slug' => 'none', 'price' => 100, 'quantity' => 0, 'supplier_quantity' => 0]);

        $rows = $this->exportedRows(['filter' => ['name' => 'В наличии']]);

        $this->assertEqualsCanonicalizing(['В наличии', 'Под заказ'], array_column($rows, 'Наименование'));
    }

    public function test_row_values(): void
    {
        $brand = Manufacturer::query()->create(['name' => 'ZUBR', 'slug' => 'zubr']);
        $product = Product::query()->create([
            'name' => 'ZUBR 60Ah', 'slug' => 'zubr-60', 'sku' => 'Z60', 'external_id' => '1C-1',
            'price' => 7600, 'special_price' => 7000, 'trade_in_discount' => 1000,
            'quantity' => 2, 'supplier_quantity' => 5, 'manufacturer_id' => $brand->id, 'image' => 'products/zubr.jpg',
        ]);

        $row = $this->exportedRows()[0];

        $this->assertSame('Z60', $row['Артикул']);
        $this->assertSame('1C-1', $row['Код 1С']);
        $this->assertSame($product->id, $row['ID товара на сайте']);
        $this->assertEquals(7600, $row['Цена']);
        $this->assertEquals(1000, $row['Скидка за старый']);
        $this->assertEquals(7000, $row['Цена на сайте']);
        $this->assertSame(2, $row['Кол-во']);
        $this->assertSame(5, $row['Количество у поставщика']);
        $this->assertSame('ZUBR', $row['Производитель']);
        $this->assertStringStartsWith('http', $row['Путь к изображению (URL)']);
        $this->assertStringEndsWith('products/zubr.jpg', $row['Путь к изображению (URL)']);
    }

    public function test_on_order_product_shows_supplier_price_and_zero_stock(): void
    {
        Product::query()->create(['name' => 'Под заказ', 'slug' => 'on-order', 'price' => 100, 'quantity' => 0, 'supplier_quantity' => 5, 'supplier_price' => 90]);

        $row = $this->exportedRows()[0];

        $this->assertEquals(90, $row['Цена на сайте']);
        $this->assertEquals(100, $row['Цена']);
        $this->assertSame(0, $row['Кол-во']);
    }

    public function test_product_without_trade_in_and_image_has_empty_cells(): void
    {
        Product::query()->create(['name' => 'Без скидки', 'slug' => 'plain', 'price' => 100, 'quantity' => 1]);

        $row = $this->exportedRows()[0];

        $this->assertEmpty($row['Скидка за старый']);
        $this->assertEmpty($row['Путь к изображению (URL)']);
    }

    public function test_category_is_root_and_subcategory_is_first_category_of_product(): void
    {
        $root = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akb']);
        $middle = Category::query()->create(['name' => 'Автомобильные', 'slug' => 'auto', 'parent_id' => $root->id]);
        $lowest = Category::query()->create(['name' => 'Аком', 'slug' => 'akom', 'parent_id' => $middle->id]);
        $other = Category::query()->create(['name' => 'Масла', 'slug' => 'oil']);

        $deep = Product::query()->create(['name' => 'Глубокий', 'slug' => 'deep', 'price' => 1, 'quantity' => 1]);
        $deep->categories()->attach([$lowest->id, $other->id]);
        $shallow = Product::query()->create(['name' => 'В корне', 'slug' => 'shallow', 'price' => 1, 'quantity' => 1]);
        $shallow->categories()->attach($root->id);
        Product::query()->create(['name' => 'Без категории', 'slug' => 'none', 'price' => 1, 'quantity' => 1]);

        $rows = collect($this->exportedRows())->keyBy('Наименование');

        $this->assertSame('Аккумуляторы', $rows['Глубокий']['Категория']);
        $this->assertSame('Аком', $rows['Глубокий']['Подкатегория']);
        $this->assertSame('Аккумуляторы', $rows['В корне']['Категория']);
        $this->assertEmpty($rows['В корне']['Подкатегория']);
        $this->assertEmpty($rows['Без категории']['Категория']);
    }

    public function test_deleted_products_are_not_exported(): void
    {
        Product::query()->create(['name' => 'Удалён', 'slug' => 'gone', 'price' => 1, 'quantity' => 1])->delete();
        Product::query()->create(['name' => 'Жив', 'slug' => 'alive', 'price' => 1, 'quantity' => 1]);

        $this->assertSame(['Жив'], array_column($this->exportedRows(), 'Наименование'));
    }
}
