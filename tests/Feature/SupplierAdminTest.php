<?php

namespace Tests\Feature;

use App\Enums\SupplierPriceFormat;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Backup\DatabaseBackup;
use App\Services\Supplier\SupplierImportFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class SupplierAdminTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]), 'moonshine');

        $this->supplier = Supplier::factory()->create(['name' => 'АКБ Дриллинг', 'format' => SupplierPriceFormat::Drilling, 'ignore_quantity' => true, 'markup_percent' => 20]);
        $this->product = Product::query()->create(['name' => 'Аккумулятор TOPLA Energy 60', 'slug' => 'topla-60', 'price' => 9000, 'quantity' => 0, 'status' => true]);
    }

    public function test_menu_and_supplier_pages_are_available(): void
    {
        $this->get('/admin')->assertSee('Поставщики')->assertSee('Прайсы поставщиков');
        $this->get('/admin/component/supplier-index-page/supplier-resource?_component_name=index-table-supplier-resource')
            ->assertOk()
            ->assertSee('АКБ Дриллинг')
            ->assertSee('не учитывается');
        $this->get('/admin/resource/supplier-resource/supplier-form-page')
            ->assertOk()
            ->assertSee('Не учитывать количество')
            ->assertSee('Срок поставки, рабочих дней')
            ->assertSee('Порог остатка, шт.');
    }

    public function test_upload_shows_report_without_changing_products(): void
    {
        $id = $this->importId($this->upload());

        $this->get("/admin/page/supplier-import?import={$id}")
            ->assertOk()
            ->assertSee('Применить к базе')
            ->assertSee('Не применено')
            ->assertSee('data-resizable', false);

        $this->assertNull($this->product->fresh()->supplier_id);
        $this->assertNotNull(app(SupplierImportFiles::class)->path($id));
    }

    public function test_apply_backs_up_and_puts_product_on_order(): void
    {
        $this->mock(DatabaseBackup::class, fn (MockInterface $backup) => $backup->shouldReceive('create')->once()->with('before-supplier-import')->andReturn('backup.sql.gz'));
        $id = $this->importId($this->upload());

        $this->postJson('/admin/method/supplier-import?'.http_build_query(['method' => 'apply', 'import' => $id]))->assertOk();

        $this->assertSame($this->supplier->id, $this->supplier->offers()->first()->supplier_id);
        $this->get("/admin/page/supplier-import?import={$id}")->assertSee('Применено');
    }

    public function test_old_xls_is_rejected(): void
    {
        $response = $this->postJson('/admin/method/supplier-import?method=upload', [
            'supplier_id' => $this->supplier->id,
            'file' => UploadedFile::fake()->create('price.xls', 10, 'application/vnd.ms-excel'),
        ]);

        $this->assertSame([], Storage::disk('local')->files('supplier-imports'));
        $response->assertJsonValidationErrors('file');
    }

    public function test_unknown_id_in_report_url_shows_nothing(): void
    {
        $this->get('/admin/page/supplier-import?import=../../.env')->assertOk()->assertDontSee('Применить к базе');
    }

    private function upload(): TestResponse
    {
        $csv = "TOPLA tab;;\nАккумулятор TOPLA Energy 60;\"7000\";\"7500\"\nНовый товар;\"100\";\"200\"\n";

        return $this->postJson('/admin/method/supplier-import?method=upload', [
            'supplier_id' => $this->supplier->id,
            'file' => UploadedFile::fake()->createWithContent('price.csv', $csv),
        ]);
    }

    private function importId(TestResponse $response): string
    {
        $redirect = (string) $response->json('redirect');
        parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);

        return (string) ($query['import'] ?? '');
    }
}
