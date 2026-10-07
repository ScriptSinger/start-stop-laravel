<?php

namespace Tests\Feature;

use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Rap2hpoutre\FastExcel\FastExcel;
use Tests\TestCase;

class ProductImportExportTest extends TestCase
{
    use RefreshDatabase;

    private Product $zubr;

    private Product $titan;

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

        $zubrBrand = Manufacturer::query()->create(['name' => 'ZUBR', 'slug' => 'zubr']);
        $this->zubr = Product::query()->create(['name' => 'ZUBR 60Ah', 'slug' => 'zubr-60', 'price' => 7600, 'quantity' => 2, 'status' => true, 'manufacturer_id' => $zubrBrand->id]);
        $this->titan = Product::query()->create(['name' => 'TITAN 60Ah', 'slug' => 'titan-60', 'price' => 8100, 'quantity' => 0, 'status' => true]);
    }

    public function test_export_respects_filters_and_has_editable_columns(): void
    {
        $response = $this->get('/admin/resource/product-resource/handler/export-handler?'.http_build_query(['filter' => ['name' => 'ZUBR']]));

        $response->assertOk();
        $rows = (new FastExcel)->import($response->baseResponse->getFile()->getPathname());

        $this->assertCount(1, $rows);
        $this->assertSame('ZUBR 60Ah', $rows[0]['Название']);
        $this->assertSame('ZUBR', $rows[0]['Производитель']);
        $this->assertArrayHasKey('Цена', $rows[0]);
        $this->assertArrayHasKey('Скидка за трейд-ин', $rows[0]);
    }

    public function test_import_updates_existing_products(): void
    {
        $this->import([
            ['ID' => $this->zubr->id, 'Название' => 'ZUBR 60Ah', 'Цена' => 7900, 'Остаток' => 5, 'Скидка за трейд-ин' => 1000, 'Активен' => 1],
            ['ID' => $this->titan->id, 'Название' => 'TITAN 60Ah', 'Цена' => 8100, 'Остаток' => 0, 'Скидка за трейд-ин' => '', 'Активен' => 0],
        ])->assertRedirect();

        $this->zubr->refresh();
        $this->titan->refresh();
        $this->assertSame(['7900.0000', 5, '1000.0000', true], [$this->zubr->price, $this->zubr->quantity, $this->zubr->trade_in_discount, $this->zubr->status]);
        $this->assertNull($this->titan->trade_in_discount);
        $this->assertFalse($this->titan->status);
        // Производитель импортом не трогается.
        $this->assertNotNull($this->zubr->manufacturer_id);
    }

    public function test_file_with_row_without_id_changes_nothing(): void
    {
        $this->import([
            ['ID' => $this->zubr->id, 'Название' => 'ZUBR 60Ah', 'Цена' => 1],
            ['ID' => '', 'Название' => 'Новый товар', 'Цена' => 5000],
        ])->assertRedirect()->assertSessionHas('toast');

        $this->assertSame('7600.0000', $this->zubr->refresh()->price);
        $this->assertSame(2, Product::query()->count());
    }

    public function test_file_with_unknown_id_changes_nothing(): void
    {
        $this->import([
            ['ID' => $this->zubr->id, 'Название' => 'ZUBR 60Ah', 'Цена' => 1],
            ['ID' => 99999, 'Название' => 'Чужой', 'Цена' => 5000],
        ])->assertRedirect();

        $this->assertSame('7600.0000', $this->zubr->refresh()->price);
        $this->assertSame(2, Product::query()->count());
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function import(array $rows): TestResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
        (new FastExcel(collect($rows)))->export($path);

        return $this->post('/admin/resource/product-resource/handler/product-import-handler', [
            'import_file' => new UploadedFile($path, 'tovary.xlsx', null, null, true),
        ]);
    }
}
