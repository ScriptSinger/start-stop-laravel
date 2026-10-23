<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SupplierOffer;
use App\Services\Import\XlsxReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class LinkSupplierOffersFromMediatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_offers_are_linked_by_sku_from_mediator_file(): void
    {
        $product = Product::query()->create(['name' => 'BOLK 100 Ah О.П.', 'slug' => 'bolk-100', 'sku' => '251', 'price' => 9000, 'quantity' => 0]);
        $byCode = SupplierOffer::factory()->create(['supplier_code' => 'УТ-00010853', 'quantity' => 9]);
        $byName = SupplierOffer::factory()->create(['supplier_code' => 'xtreme +efb 100 обр (125d31l)', 'quantity' => 9]);
        $other = SupplierOffer::factory()->create(['supplier_code' => 'УТ-1']);
        $xtreme = Product::query()->create(['name' => 'XTREME EFB 100Ah О.П.', 'slug' => 'xtreme-100', 'sku' => '463', 'price' => 9000, 'quantity' => 0]);

        $this->mock(XlsxReader::class, fn (MockInterface $reader) => $reader->shouldReceive('rows')->andReturnUsing(function (string $path, int $sheet): \Generator {
            yield $sheet === 1
                ? ['B' => 'УТ-00010853', 'C' => 'BOLK 6ст-100', 'I' => '251']
                : ['B' => '', 'C' => 'XTREME +EFB  100 обр (125D31L)', 'I' => '463'];
        }));

        $this->artisan('suppliers:link-mediator', ['file' => 'x.xlsx'])->assertSuccessful();
        $this->assertNull($byCode->fresh()->product_id, 'без --force ничего не записывается');

        $this->artisan('suppliers:link-mediator', ['file' => 'x.xlsx', '--force' => true])->assertSuccessful();

        $this->assertSame($product->id, $byCode->fresh()->product_id);
        $this->assertSame($xtreme->id, $byName->fresh()->product_id);
        $this->assertNull($other->fresh()->product_id);
    }
}
