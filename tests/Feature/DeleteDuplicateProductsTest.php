<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\Catalog\ProductDuplicates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeleteDuplicateProductsTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        array_map('unlink', $this->files);

        parent::tearDown();
    }

    public function test_names_are_matched_regardless_of_case_spacing_and_polarity_notation(): void
    {
        $finder = new ProductDuplicates;

        $this->assertSame($finder->key('DELKOR 100 Ah Asia О.П.'), $finder->key('delkor 100Ah АЗИЯ о.п.'));
        $this->assertSame($finder->key('FIREBALL 62 Ah О.П.'), $finder->key('Fire Ball 62Ah о.п.'));
        $this->assertNotSame($finder->key('DELKOR 100 Ah О.П.'), $finder->key('DELKOR 100 Ah П.П.'));
        $this->assertNotSame($finder->key('DELKOR 60 Ah О.П.'), $finder->key('DELKOR 65 Ah О.П.'));
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $this->product('DELKOR 60 Ah О.П.', ['status' => false]);
        $this->product('Delkor 60Ah о.п.', ['status' => false]);

        $this->artisan('products:delete-duplicates')
            ->expectsOutputToContain('Пробный прогон')
            ->assertSuccessful();

        $this->assertSame(2, Product::query()->count());
    }

    public function test_force_soft_deletes_duplicates_and_keeps_the_one_with_data(): void
    {
        $category = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori']);
        $bare = $this->product('DELKOR 60 Ah О.П.', ['status' => false]);
        $withData = $this->product('Delkor 60Ah о.п.', ['status' => false]);
        $category->products()->attach($withData);
        $other = $this->product('DELKOR 65 Ah О.П.');

        $this->artisan('products:delete-duplicates', ['--force' => true])->assertSuccessful();

        $this->assertSoftDeleted($bare);
        $this->assertNotSoftDeleted($withData);
        $this->assertNotSoftDeleted($other);
        $this->assertSame(1, Product::onlyTrashed()->count());
    }

    public function test_product_with_orders_is_kept(): void
    {
        $withoutOrders = $this->product('ZUBR 60 Ah О.П.', ['status' => false]);
        $withOrders = $this->product('Zubr 60Ah о.п.', ['status' => false]);
        $this->orderItemFor($withOrders);

        $this->artisan('products:delete-duplicates', ['--force' => true])->assertSuccessful();

        $this->assertNotSoftDeleted($withOrders);
        $this->assertSoftDeleted($withoutOrders);
    }

    public function test_duplicates_with_orders_make_group_disputed(): void
    {
        $first = $this->product('ZUBR 60 Ah О.П.');
        $second = $this->product('Zubr 60Ah о.п.');
        $this->orderItemFor($first);
        $this->orderItemFor($second);

        $this->artisan('products:delete-duplicates', ['--force' => true])
            ->expectsOutputToContain('Спорных групп')
            ->assertSuccessful();

        $this->assertNotSoftDeleted($first);
        $this->assertNotSoftDeleted($second);
    }

    public function test_group_with_different_prices_or_skus_is_disputed_and_untouched(): void
    {
        $cheap = $this->product('TITAN 75 Ah П.П.', ['price' => 7800]);
        $pricey = $this->product('Titan 75Ah п.п.', ['price' => 8500]);
        $first = $this->product('BORG 60 Ah П.П.', ['sku' => '1']);
        $second = $this->product('Borg 60Ah п.п.', ['sku' => '2']);

        $this->artisan('products:delete-duplicates', ['--force' => true])
            ->expectsOutputToContain('разная цена')
            ->expectsOutputToContain('разные sku')
            ->assertSuccessful();

        foreach ([$cheap, $pricey, $first, $second] as $product) {
            $this->assertNotSoftDeleted($product);
        }
    }

    public function test_already_deleted_products_are_ignored(): void
    {
        $kept = $this->product('DELKOR 60 Ah О.П.', ['status' => false]);
        $deleted = $this->product('Delkor 60Ah о.п.', ['status' => false]);
        $deleted->delete();

        $this->artisan('products:delete-duplicates', ['--force' => true])->assertSuccessful();

        $this->assertNotSoftDeleted($kept);
        $this->assertSame(1, Product::onlyTrashed()->count());
    }

    public function test_report_lists_keep_and_delete_actions(): void
    {
        $this->product('DELKOR 60 Ah О.П.', ['status' => false]);
        $this->product('Delkor 60Ah о.п.', ['status' => false]);
        $report = tempnam(sys_get_temp_dir(), 'dup');
        $this->files[] = $report;

        $this->artisan('products:delete-duplicates', ['--report' => $report])->assertSuccessful();

        $csv = file_get_contents($report);
        $this->assertStringContainsString('оставить', $csv);
        $this->assertStringContainsString('удалить', $csv);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $name, array $attributes = []): Product
    {
        return Product::query()->create($attributes + ['name' => $name, 'slug' => str($name)->slug()->value().'-'.Product::withTrashed()->count(), 'price' => 7000, 'status' => true]);
    }

    private function orderItemFor(Product $product): void
    {
        $order = Order::query()->create(['customer_name' => 'Иван', 'customer_phone' => '+79990000000', 'total' => 7000, 'status' => 'new']);

        DB::table('order_items')->insert(['order_id' => $order->id, 'product_id' => $product->id, 'name' => $product->name, 'price' => 7000, 'quantity' => 1, 'total' => 7000]);
    }
}
