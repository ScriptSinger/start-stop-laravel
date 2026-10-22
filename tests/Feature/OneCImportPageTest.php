<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Backup\DatabaseBackup;
use App\Services\Import\OneCImportFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use RuntimeException;
use Tests\TestCase;

class OneCImportPageTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = "\xEF\xBB\xBFКод;Артикул;Наименование;Свободный остаток;Розничная цена\r\n";

    private Product $akom;

    private Product $oil;

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

        $this->akom = Product::query()->create(['name' => 'AKOM 75 Ah О.П.', 'slug' => 'akom-75', 'price' => 9800, 'quantity' => 1, 'external_id' => 'РТ-00000789']);
        $this->oil = Product::query()->create(['name' => 'G-Energy Active 5w30 1л', 'slug' => 'g-energy', 'price' => 650, 'quantity' => 2, 'external_id' => '291']);
    }

    public function test_page_shows_upload_form_and_is_in_menu(): void
    {
        $this->get('/admin/page/one-c-import')
            ->assertOk()
            ->assertSee('Загрузить прайс')
            ->assertSee('name="file"', false)
            ->assertSee('Последние загрузки');

        $this->get('/admin')->assertSee('Загрузка из 1С');
    }

    public function test_upload_stores_file_and_redirects_to_report_without_changing_data(): void
    {
        $response = $this->upload($this->priceList());

        $response->assertOk();
        $id = $this->importId($response);
        $this->assertNotNull(app(OneCImportFiles::class)->path($id));
        $this->assertSame(9800.0, (float) $this->akom->fresh()->price);
    }

    public function test_report_shows_summary_changes_and_missing_products(): void
    {
        $id = $this->importId($this->upload($this->priceList()));

        $this->get("/admin/page/one-c-import?import={$id}")
            ->assertOk()
            ->assertSee('Найдено на сайте')
            ->assertSee('AKOM 75 Ah О.П.')
            ->assertSee('9 800 ₽ → 11 600 ₽')
            ->assertSee('Новый товар из 1С')
            ->assertSee('Применить к базе')
            ->assertSee('Не применено');
    }

    public function test_report_flags_suspicious_changes(): void
    {
        $id = $this->importId($this->upload($this->priceList()));

        $this->get("/admin/page/one-c-import?import={$id}")
            ->assertSee('цена в 1С меньше 10 ₽')
            ->assertSee('остаток не указан в 1С — будет 0');
    }

    public function test_apply_backs_up_database_and_updates_prices_and_stock(): void
    {
        $this->mock(DatabaseBackup::class, fn (MockInterface $backup) => $backup->shouldReceive('create')->once()->with('before-onec-import')->andReturn('backup.sql.gz'));
        $id = $this->importId($this->upload($this->priceList()));

        $this->applyImport($id)->assertOk();

        $this->assertSame(11600.0, (float) $this->akom->fresh()->price);
        $this->assertSame(5, $this->akom->fresh()->quantity);
        $this->assertSame(1.0, (float) $this->oil->fresh()->price);
        $this->assertSame('AKOM 75 Ah О.П.', $this->akom->fresh()->name);

        $this->get("/admin/page/one-c-import?import={$id}")->assertSee('Применено');
        $this->assertArrayHasKey('applied_at', app(OneCImportFiles::class)->meta($id));
    }

    public function test_apply_does_nothing_when_backup_fails(): void
    {
        $this->mock(DatabaseBackup::class, fn (MockInterface $backup) => $backup->shouldReceive('create')->andThrow(new RuntimeException('нет места')));
        $id = $this->importId($this->upload($this->priceList()));

        $this->applyImport($id)->assertOk();

        $this->assertSame(9800.0, (float) $this->akom->fresh()->price);
        $this->assertArrayNotHasKey('applied_at', app(OneCImportFiles::class)->meta($id));
    }

    public function test_apply_with_unknown_or_malicious_id_changes_nothing(): void
    {
        $this->mock(DatabaseBackup::class, fn (MockInterface $backup) => $backup->shouldReceive('create')->never());

        $this->applyImport('../../.env')->assertOk();
        $this->applyImport('20250101-000000-abcdef')->assertOk();

        $this->assertSame(9800.0, (float) $this->akom->fresh()->price);
    }

    public function test_report_with_malicious_id_shows_no_report(): void
    {
        $this->get('/admin/page/one-c-import?import=../../.env')
            ->assertOk()
            ->assertDontSee('Применить к базе');
    }

    public function test_file_with_unknown_format_is_rejected_and_not_kept(): void
    {
        $response = $this->upload("a;b;c\r\n1;2;3\r\n");

        $response->assertOk();
        $this->assertSame([], Storage::disk('local')->files('onec-imports'));
    }

    public function test_recent_uploads_are_listed(): void
    {
        $this->upload($this->priceList('моя_выгрузка.csv'));

        $this->get('/admin/page/one-c-import')->assertSee('моя_выгрузка.csv')->assertSee('не применено');
    }

    private function priceList(string $name = 'price_list.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, self::HEADER
            .'РТ-00000789;;"Аккумулятор AKOM 75Ah о.п. 750A";5.000;11600.00'."\r\n"
            .'291;;"Моторное Масло G-Energy 1л";;1.00'."\r\n"
            .'РТ-00000999;;"Новый товар из 1С";1.000;500.00'."\r\n");
    }

    private function upload(UploadedFile|string $file): TestResponse
    {
        $file = is_string($file) ? UploadedFile::fake()->createWithContent('bad.csv', $file) : $file;

        return $this->postJson('/admin/method/one-c-import?method=upload', ['file' => $file]);
    }

    private function applyImport(string $id): TestResponse
    {
        return $this->postJson('/admin/method/one-c-import?'.http_build_query(['method' => 'apply', 'import' => $id]));
    }

    private function importId(TestResponse $response): string
    {
        $redirect = (string) $response->json('redirect');
        parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);

        $this->assertNotEmpty($query['import'] ?? null, 'В ответе нет ссылки на отчёт: '.$response->getContent());

        return $query['import'];
    }
}
