<?php

namespace Tests\Feature;

use App\Enums\SupplierPriceFormat;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use App\Services\Supplier\SupplierImportReport;
use App\Services\Supplier\SupplierPriceImporter;
use App\Services\Supplier\SupplierStockSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupplierPriceImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_drilling_price_without_quantity_goes_on_order_with_markup(): void
    {
        $supplier = Supplier::factory()->create(['format' => SupplierPriceFormat::Drilling, 'ignore_quantity' => true, 'markup_percent' => 20]);
        $product = $this->product('Аккумулятор TOPLA Energy 6СТ 60 0(R+)');

        $report = $this->import($supplier, "TOPLA tab;;\nАккумулятор TOPLA  Energy 6СТ  60 0(R+);\"7017,65\";\"7789,6\"\n", true);

        $this->assertSame(1, $report->rows);
        $this->assertSame(1, $report->linkedByName);
        $product->refresh();
        $this->assertSame($supplier->id, $product->supplier_id);
        $this->assertSame(9350.0, (float) $product->supplier_price); // 7789,6 * 1,2 = 9347,52
        $this->assertTrue($product->isAvailableOnOrder());
    }

    public function test_quantity_below_supplier_threshold_is_not_on_order(): void
    {
        $supplier = Supplier::factory()->create(['min_quantity' => 3]);
        $product = $this->product('BOLK Asia 6ст-50 Ач');

        $this->import($supplier, $this->akbAutoCsv('УТ-1', 'BOLK Asia 6ст-50 Ач', '3700', '2'), true);
        $this->assertFalse($product->refresh()->isAvailableOnOrder());

        $this->import($supplier, $this->akbAutoCsv('УТ-1', 'BOLK Asia 6ст-50 Ач', '3700', '3'), true);
        $this->assertTrue($product->refresh()->isAvailableOnOrder());
    }

    public function test_dry_run_changes_nothing_but_reports_the_same(): void
    {
        $supplier = Supplier::factory()->create();
        $product = $this->product('BOLK Asia 6ст-50 Ач');

        $report = $this->import($supplier, $this->akbAutoCsv('УТ-1', 'BOLK Asia 6ст-50 Ач', '3700', '9'), false);

        $this->assertSame(1, $report->created);
        $this->assertSame(1, $report->productsAdded);
        $this->assertSame(0, $report->productsRepriced);
        $this->assertSame(0, SupplierOffer::query()->count());
        $this->assertNull($product->refresh()->supplier_id);
    }

    public function test_preview_executes_no_writes_and_matches_applied_report(): void
    {
        $supplier = Supplier::factory()->create();
        $this->product('BOLK Asia 6ст-50 Ач');
        $csv = $this->akbAutoCsv('УТ-1', 'BOLK Asia 6ст-50 Ач', '3700', '9');
        $writes = [];
        DB::listen(function ($query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        $preview = $this->import($supplier, $csv, false);
        $this->assertSame([], $writes);
        $applied = $this->import($supplier, $csv, true);
        $this->assertEquals($applied, $preview);
    }

    public function test_preview_matches_application_when_offers_change_and_compete(): void
    {
        $supplier = Supplier::factory()->create(['markup_percent' => 20]);
        $competitor = Supplier::factory()->create(['markup_percent' => 20]);
        $changed = $this->product('Changed');
        $removed = $this->product('Removed');
        $this->import($supplier, $this->akbAutoCsv('УТ-old', 'Changed', '1000', '9').$this->akbAutoCsv('УТ-gone', 'Removed', '1000', '9'), true);
        SupplierOffer::factory()->create(['supplier_id' => $competitor->id, 'product_id' => $changed->id, 'cost' => 2000, 'quantity' => 9, 'in_price' => true]);
        $csv = $this->akbAutoCsv('УТ-old', 'Changed', '2000', '9').$this->akbAutoCsv('УТ-old', 'Changed', '999', '9');

        $preview = $this->import($supplier, $csv, false);
        $applied = $this->import($supplier, $csv, true);

        $this->assertEquals($applied, $preview);
        $this->assertSame(1, $preview->vanished);
        $this->assertSame(1, $preview->duplicates);
        $this->assertSame(1, $preview->productsCleared);
        $this->assertNull($removed->refresh()->supplier_id);
        $this->assertSame($supplier->id, $changed->refresh()->supplier_id);
    }

    public function test_report_counts_only_what_changes_on_the_site(): void
    {
        $supplier = Supplier::factory()->create(['markup_percent' => 10]);
        $this->product('BOLK Asia 6ст-50 Ач');

        $first = $this->import($supplier, $this->akbAutoCsv('УТ-1', 'BOLK Asia 6ст-50 Ач', '3700', '9'), true);
        $same = $this->import($supplier, $this->akbAutoCsv('УТ-1', 'BOLK Asia 6ст-50 Ач', '3700', '9'), true);
        $dearer = $this->import($supplier, $this->akbAutoCsv('УТ-1', 'BOLK Asia 6ст-50 Ач', '4200', '9'), true);
        $gone = $this->import($supplier, $this->akbAutoCsv('УТ-2', 'Другой товар', '100', '9'), true);

        $this->assertSame([1, 0, 0], [$first->productsAdded, $first->productsRepriced, $first->productsCleared]);
        $this->assertSame([0, 0, 0], [$same->productsAdded, $same->productsRepriced, $same->productsCleared]);
        $this->assertSame([0, 1, 0], [$dearer->productsAdded, $dearer->productsRepriced, $dearer->productsCleared]);
        $this->assertSame([0, 0, 1], [$gone->productsAdded, $gone->productsRepriced, $gone->productsCleared]);
    }

    public function test_cheapest_supplier_wins_and_vanished_offer_is_cleared(): void
    {
        $cheap = Supplier::factory()->create(['markup_percent' => 10]);
        $dear = Supplier::factory()->create(['markup_percent' => 30]);
        $product = $this->product('BOLK Asia 6ст-50 Ач');

        $this->import($dear, $this->akbAutoCsv('УТ-1', 'BOLK Asia 6ст-50 Ач', '3700', '9'), true);
        $this->import($cheap, $this->akbAutoCsv('УТ-7', 'BOLK Asia 6ст-50 Ач', '3700', '9'), true);

        $this->assertSame($cheap->id, $product->refresh()->supplier_id);
        $this->assertSame(4100.0, (float) $product->supplier_price);

        $report = $this->import($cheap, $this->akbAutoCsv('УТ-8', 'Другой товар', '100', '9'), true);

        $this->assertSame(1, $report->vanished);
        $this->assertSame($dear->id, $product->refresh()->supplier_id);
    }

    public function test_one_c_stock_keeps_priority_over_supplier_price(): void
    {
        $supplier = Supplier::factory()->create();
        $product = $this->product('BOLK Asia 6ст-50 Ач', ['quantity' => 4, 'price' => 5000]);

        $this->import($supplier, $this->akbAutoCsv('УТ-1', 'BOLK Asia 6ст-50 Ач', '3700', '9'), true);

        $this->assertFalse($product->refresh()->isAvailableOnOrder());
        $this->assertSame(5000.0, $product->displayPrice());
    }

    public function test_product_page_shows_expected_date_without_quantity(): void
    {
        $this->travelTo('2026-10-23 10:00:00'); // пятница
        $supplier = Supplier::factory()->create(['lead_working_days' => 2]);
        $product = $this->product('BOLK Asia 6ст-50 Ач', ['slug' => 'bolk-50']);

        $this->import($supplier, $this->akbAutoCsv('УТ-1', 'BOLK Asia 6ст-50 Ач', '3700', '9'), true);

        $this->get(route('product.show', $product))
            ->assertOk()
            ->assertSee('Под заказ, ожидаем 27 октября')
            ->assertDontSee('9 шт');
    }

    public function test_changing_markup_reprices_products_immediately(): void
    {
        $supplier = Supplier::factory()->create(['markup_percent' => 20, 'ignore_quantity' => true]);
        $product = $this->product('Аккумулятор TOPLA Energy 6СТ 60 0(R+)');
        SupplierOffer::factory()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'cost' => 1000,
            'quantity' => 10,
            'in_price' => true,
        ]);

        $supplier->update(['markup_percent' => 30]);
        $this->assertSame(1300.0, (float) $product->refresh()->supplier_price);

        $supplier->update(['markup_percent' => 50]);
        $this->assertSame(1500.0, (float) $product->refresh()->supplier_price);

        $supplier->update(['is_active' => false]);
        $this->assertNull($product->refresh()->supplier_id);
    }

    public function test_changing_supplier_recalculates_only_related_products_and_considers_competitors(): void
    {
        $supplier = Supplier::factory()->create(['markup_percent' => 10]);
        $competitor = Supplier::factory()->create(['markup_percent' => 30]);
        $related = $this->product('Related');
        $unrelated = $this->product('Unrelated');
        SupplierOffer::factory()->create(['supplier_id' => $supplier->id, 'product_id' => $related->id, 'cost' => 1000, 'quantity' => 9, 'in_price' => true]);
        SupplierOffer::factory()->create(['supplier_id' => $competitor->id, 'product_id' => $related->id, 'cost' => 1000, 'quantity' => 9, 'in_price' => true]);
        SupplierOffer::factory()->create(['supplier_id' => $competitor->id, 'product_id' => $unrelated->id, 'cost' => 2000, 'quantity' => 9, 'in_price' => true]);
        app(SupplierStockSync::class)->sync();
        $unrelated->update(['supplier_price' => 9999]);

        $supplier->update(['markup_percent' => 50]);

        $this->assertSame($competitor->id, $related->refresh()->supplier_id);
        $this->assertSame(1300.0, (float) $related->supplier_price);
        $this->assertSame(9999.0, (float) $unrelated->refresh()->supplier_price);

        $competitor->update(['is_active' => false]);
        $this->assertSame($supplier->id, $related->refresh()->supplier_id);
        $this->assertNull($unrelated->refresh()->supplier_id);
    }

    public function test_deleting_supplier_clears_product_without_an_alternative(): void
    {
        $supplier = Supplier::factory()->create();
        $product = $this->product('Battery');
        SupplierOffer::factory()->create(['supplier_id' => $supplier->id, 'product_id' => $product->id, 'cost' => 1000, 'quantity' => 9, 'in_price' => true]);
        app(SupplierStockSync::class)->sync();

        $this->assertSame($supplier->id, $product->refresh()->supplier_id);
        $supplier->delete();

        $product->refresh();
        $this->assertNull($product->supplier_id);
        $this->assertNull($product->supplier_price);
        $this->assertSame(0, $product->supplier_quantity);
        $this->assertFalse($product->isAvailableOnOrder());
    }

    public function test_deleting_supplier_selects_remaining_offer(): void
    {
        $cheap = Supplier::factory()->create(['markup_percent' => 10]);
        $alternative = Supplier::factory()->create(['markup_percent' => 30]);
        $product = $this->product('Battery');
        SupplierOffer::factory()->create(['supplier_id' => $alternative->id, 'product_id' => $product->id, 'cost' => 1000, 'quantity' => 9, 'in_price' => true]);
        SupplierOffer::factory()->create(['supplier_id' => $cheap->id, 'product_id' => $product->id, 'cost' => 1000, 'quantity' => 9, 'in_price' => true]);
        app(SupplierStockSync::class)->sync();

        $this->assertSame($cheap->id, $product->refresh()->supplier_id);
        $cheap->delete();

        $product->refresh();
        $this->assertSame($alternative->id, $product->supplier_id);
        $this->assertSame(1300.0, (float) $product->supplier_price);
        $this->assertTrue($product->isAvailableOnOrder());
    }

    private function import(Supplier $supplier, string $csv, bool $apply): SupplierImportReport
    {
        $path = tempnam(sys_get_temp_dir(), 'sup').'.csv';
        file_put_contents($path, $csv);

        return app(SupplierPriceImporter::class)->run($supplier, $path, $apply);
    }

    private function akbAutoCsv(string $code, string $name, string $cost, string $quantity): string
    {
        // A код, M название, S цена, T остаток
        $cells = array_fill(0, 20, '');
        $cells[0] = $code;
        $cells[12] = $name;
        $cells[18] = $cost;
        $cells[19] = $quantity;

        return implode(';', $cells)."\n";
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $name, array $attributes = []): Product
    {
        return Product::query()->create([
            'name' => $name,
            'slug' => 'p-'.uniqid(),
            'price' => 10000,
            'quantity' => 0,
            'status' => true,
            ...$attributes,
        ]);
    }
}
