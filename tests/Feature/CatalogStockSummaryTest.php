<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\BatteryFitment;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\Catalog\AssortmentGaps;
use App\Services\Catalog\StockSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogStockSummaryTest extends TestCase
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
        config(['shop.battery_fitment.category_ids' => [$this->batteries->id]]);

        $this->product('В наличии', quantity: 3, price: 7000, manufacturer: $this->zubr);
        $this->product('Ещё в наличии', quantity: 1, price: 5000);
        $this->product('Под заказ', quantity: 0, supplierQuantity: 10, manufacturer: $this->zubr);
        $this->product('Мало у поставщика', quantity: 0, supplierQuantity: 2, manufacturer: $this->zubr);
        $this->product('Неактивный', quantity: 50, status: false, manufacturer: $this->zubr);
    }

    public function test_filtered_summary_counts_each_product_once_and_excludes_other_categories(): void
    {
        $secondCategory = Category::query()->create(['name' => 'Грузовые', 'slug' => 'cargo']);
        Product::query()->where('name', 'В наличии')->firstOrFail()->categories()->attach($secondCategory);
        Product::query()->create(['name' => 'Другое', 'slug' => 'other', 'price' => 100, 'quantity' => 5, 'status' => true]);

        $summary = app(StockSummary::class)->overall([$this->batteries->id, $secondCategory->id]);

        $this->assertSame(4, $summary['total']);
        $this->assertSame(2, $summary['in_stock']);
        $this->assertSame(1, $summary['on_order']);
        $this->assertSame(1, $summary['out_of_stock']);
    }

    public function test_overall_counts_only_active_products(): void
    {
        $this->assertSame([
            'total' => 4,
            'in_stock' => 2,
            'on_order' => 1,
            'out_of_stock' => 1,
        ], app(StockSummary::class)->overall());
    }

    public function test_assortment_gaps_count_cars_without_batteries_by_size(): void
    {
        Attribute::query()->forceCreate(['id' => 13, 'name' => 'Полярность'])->values()->createMany([['value' => 'Обратная'], ['value' => 'Прямая']]);
        Attribute::query()->forceCreate(['id' => 20, 'name' => 'Ёмкость (Ah)'])->values()->create(['value' => '55 - 65 Ah']);
        Attribute::query()->forceCreate(['id' => 16, 'name' => 'Габариты'])->values()->createMany([['value' => 'Евро L2 (242 x 175 x 190 мм)'], ['value' => 'Азия B24 (234 x 127 x 227 мм)']]);

        $battery = Product::query()->where('name', 'В наличии')->first();
        $battery->attributeValues()->attach(AttributeValue::query()->whereIn('value', ['Обратная', '55 - 65 Ah', 'Евро L2 (242 x 175 x 190 мм)'])->pluck('id'));

        BatteryFitment::query()->create(['brand' => 'Kia', 'model' => 'Rio', 'capacity' => '60 Ач', 'polarity' => 'Обратная', 'dims' => '242x175x190']);
        BatteryFitment::query()->create(['brand' => 'Honda', 'model' => 'Fit', 'capacity' => '60 Ач', 'polarity' => 'Обратная', 'dims' => '236x128x220']);
        BatteryFitment::query()->create(['brand' => 'Honda', 'model' => 'Jazz', 'capacity' => '60 Ач', 'polarity' => 'Обратная', 'dims' => '236x128x220']);
        BatteryFitment::query()->create(['brand' => 'Грузовики', 'model' => 'КамАЗ', 'capacity' => '190 Ач', 'polarity' => 'Обратная']);
        config(['shop.car_landings.excluded_brands' => ['Грузовики']]);

        $this->assertSame([
            'cars' => 3,
            'without_batteries' => 2,
            'sizes' => [['size' => 'Азия B24 (234 x 127 x 227 мм)', 'polarity' => 'Обратная', 'cars' => 2]],
        ], app(AssortmentGaps::class)->refresh());

        $this->artisan('assortment:gaps')->expectsOutputToContain('Машин без подходящего АКБ: 2 из 3')->assertSuccessful();
    }

    public function test_batteries_without_fitment_data_are_found(): void
    {
        $this->assertSame(['В наличии', 'Ещё в наличии', 'Под заказ', 'Мало у поставщика', 'Неактивный'], Product::query()->missingFitmentData()->orderBy('id')->pluck('name')->all());
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
