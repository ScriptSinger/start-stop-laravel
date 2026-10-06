<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\Catalog\StockSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class AdminStockReportTest extends TestCase
{
    use RefreshDatabase;

    private Manufacturer $zubr;

    private Category $batteries;

    protected function setUp(): void
    {
        parent::setUp();

        config(['shop.supplier_order_min_quantity' => 5]);

        $this->zubr = Manufacturer::query()->create(['name' => 'ZUBR', 'slug' => 'zubr']);
        $this->batteries = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori']);

        $this->product('В наличии', quantity: 3, price: 7000, manufacturer: $this->zubr);
        $this->product('Ещё в наличии', quantity: 1, price: 5000);
        $this->product('Под заказ', quantity: 0, supplierQuantity: 10, manufacturer: $this->zubr);
        $this->product('Мало у поставщика', quantity: 0, supplierQuantity: 2, manufacturer: $this->zubr);
        $this->product('Неактивный', quantity: 50, status: false, manufacturer: $this->zubr);
    }

    public function test_overall_counts_only_active_products(): void
    {
        $this->assertSame([
            'total' => 4,
            'in_stock' => 2,
            'units' => 4,
            'on_order' => 1,
            'out_of_stock' => 1,
            'stock_value' => 26000.0,
        ], app(StockSummary::class)->overall());
    }

    public function test_breakdown_by_manufacturer_and_category(): void
    {
        $summary = app(StockSummary::class);

        $this->assertSame(
            [
                ['name' => 'ZUBR', 'total' => 3, 'in_stock' => 1, 'units' => 3, 'on_order' => 1, 'out_of_stock' => 1],
                ['name' => 'Без производителя', 'total' => 1, 'in_stock' => 1, 'units' => 1, 'on_order' => 0, 'out_of_stock' => 0],
            ],
            $summary->byManufacturer()->map(fn (array $row): array => array_intersect_key($row, array_flip(['name', 'total', 'in_stock', 'units', 'on_order', 'out_of_stock'])))->all(),
        );

        $this->assertSame([['Аккумуляторы', 4, 4]], $summary->byCategory()->map(fn (array $row): array => [$row['name'], $row['total'], $row['units']])->all());
    }

    public function test_page_shows_summary_with_links_to_filtered_products(): void
    {
        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]), 'moonshine');

        $this->get('/admin/page/stock-report')
            ->assertOk()
            ->assertSee('2 из 4')
            ->assertSee('26 000 ₽')
            ->assertSee('По производителям')
            ->assertSee('ZUBR')
            ->assertSee('filter%5Bstatus%5D=1&amp;filter%5Bmanufacturer_id%5D='.$this->zubr->id.'&amp;filter%5Bstock%5D=order', false)
            ->assertSee('filter%5Bcategories%5D%5B0%5D='.$this->batteries->id, false)
            ->assertSee('filter%5Bstatus%5D=1&amp;filter%5Bwithout_manufacturer%5D=1', false)
            ->assertDontSee('>http://localhost/admin', false);
    }

    private function product(string $name, int $quantity, int $price = 1000, int $supplierQuantity = 0, bool $status = true, ?Manufacturer $manufacturer = null): void
    {
        $product = Product::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'price' => $price,
            'quantity' => $quantity,
            'supplier_quantity' => $supplierQuantity,
            'status' => $status,
            'manufacturer_id' => $manufacturer?->id,
        ]);

        $product->categories()->attach($this->batteries);
    }
}
