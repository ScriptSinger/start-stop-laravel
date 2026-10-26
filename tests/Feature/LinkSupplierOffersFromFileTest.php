<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkSupplierOffersFromFileTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = tempnam(sys_get_temp_dir(), 'links').'.csv';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    public function test_dry_run_changes_nothing_and_force_links_offers_by_code_or_name(): void
    {
        $akb = Supplier::factory()->create(['name' => 'АКБ АВТО']);
        $sib = Supplier::factory()->create(['name' => 'СибЭлектроИсточник', 'markup_percent' => 10]);
        $byCode = SupplierOffer::factory()->create(['supplier_id' => $akb->id, 'supplier_code' => 'УТ-00010824', 'quantity' => 9, 'cost' => 3700]);
        $byName = SupplierOffer::factory()->create(['supplier_id' => $sib->id, 'supplier_code' => 'xtreme +efb 100 обр (125d31l)', 'quantity' => 9, 'cost' => 10340]);
        $first = $this->product('BOLK 50 Ah П.П.');
        $second = $this->product('XTREME EFB 100Ah О.П.');

        $this->writeCsv([
            ['АКБ АВТО', 'УТ-00010824', 'BOLK Asia 6ст-50 Ач', $first->id],
            ['СибЭлектроИсточник', '', '  XTREME +EFB  100 обр (125D31L) ', $second->id],
        ]);

        $this->artisan('suppliers:apply-links', ['file' => $this->path])->assertSuccessful();
        $this->assertNull($byCode->fresh()->product_id);

        $this->artisan('suppliers:apply-links', ['file' => $this->path, '--force' => true])->assertSuccessful();

        $this->assertSame($first->id, $byCode->fresh()->product_id);
        $this->assertSame($second->id, $byName->fresh()->product_id);
        $this->assertTrue($first->fresh()->isAvailableOnOrder());
        $this->assertSame(11400.0, (float) $second->fresh()->supplier_price); // 10340 * 1,1 = 11374 → 11400
    }

    public function test_existing_other_link_is_not_overwritten_and_ambiguous_lines_are_skipped(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'АКБ Дриллинг']);
        $linked = SupplierOffer::factory()->create(['supplier_id' => $supplier->id, 'supplier_code' => 'unicum 60 оп/пп', 'quantity' => 1]);
        $ambiguous = SupplierOffer::factory()->create(['supplier_id' => $supplier->id, 'supplier_code' => 'magnum 60 оп/пп', 'quantity' => 1]);
        $old = $this->product('Старый');
        $linked->update(['product_id' => $old->id]);
        $a = $this->product('UNICUM 60Ah О.П.');
        $b = $this->product('Magnum 60Ah О.П.');
        $c = $this->product('Magnum 60Ah П.П.');

        $this->writeCsv([
            ['АКБ Дриллинг', '', 'UNICUM 60 оп/пп', $a->id],
            ['АКБ Дриллинг', '', 'Magnum 60 оп/пп', $b->id],
            ['АКБ Дриллинг', '', 'Magnum 60 оп/пп', $c->id],
            ['АКБ Дриллинг', '', 'Нет такого в базе', $a->id],
        ]);

        $this->artisan('suppliers:apply-links', ['file' => $this->path, '--force' => true])->assertSuccessful();

        $this->assertSame($old->id, $linked->fresh()->product_id);
        $this->assertNull($ambiguous->fresh()->product_id);
    }

    public function test_missing_column_is_an_error(): void
    {
        file_put_contents($this->path, "Поставщик;Название\nАКБ АВТО;x\n");

        $this->artisan('suppliers:apply-links', ['file' => $this->path])->assertFailed();
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3: int}>  $rows
     */
    private function writeCsv(array $rows): void
    {
        $handle = fopen($this->path, 'w');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['Поставщик', 'Код у поставщика', 'Название в прайсе', 'Название в стиле 1С', 'ID на сайте'], ';', '"', '');

        foreach ($rows as [$supplier, $code, $name, $productId]) {
            fputcsv($handle, [$supplier, $code, $name, '', $productId], ';', '"', '');
        }

        fclose($handle);
    }

    private function product(string $name): Product
    {
        return Product::query()->create(['name' => $name, 'slug' => 'p-'.uniqid(), 'price' => 10000, 'quantity' => 0, 'status' => true]);
    }
}
