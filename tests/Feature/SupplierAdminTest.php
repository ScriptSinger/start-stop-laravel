<?php

namespace Tests\Feature;

use App\Enums\SupplierPriceFormat;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use App\MoonShine\Resources\Product\ProductResource;
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

    public function test_application_marker_failure_rolls_back_import(): void
    {
        $this->mock(DatabaseBackup::class, fn (MockInterface $backup) => $backup->shouldReceive('create')->once()->andReturn('backup.sql.gz'));
        $id = $this->importId($this->upload());
        $this->partialMock(SupplierImportFiles::class, fn (MockInterface $files) => $files->shouldReceive('markApplied')->once()->andThrow(new \RuntimeException('Не удалось сохранить отметку')));

        $this->postJson('/admin/method/supplier-import?method=apply', ['import' => $id])->assertOk()->assertJsonPath('messageType', 'error');

        $this->assertSame(0, $this->supplier->offers()->count());
        $this->assertArrayNotHasKey('applied_at', app(SupplierImportFiles::class)->meta($id));
    }

    public function test_draft_survives_session_loss(): void
    {
        $this->mock(DatabaseBackup::class, fn (MockInterface $backup) => $backup->shouldReceive('create')->once()->andReturn('backup.sql.gz'));
        $id = $this->importId($this->upload());
        $this->postJson('/admin/method/supplier-import?method=apply', ['import' => $id])->assertOk();
        $offer = $this->supplier->offers()->whereNull('product_id')->firstOrFail();
        $this->postJson('/admin/method/supplier-import?method=linkOffer', [
            'import' => $id, 'offer_id' => $offer->id, 'product_id' => $this->product->id,
        ])->assertOk();

        $this->app['session']->flush();

        $this->get('/admin/page/supplier-import?import='.$id)->assertSee('На проверке (1)');
        $this->assertNull($offer->fresh()->product_id);
    }

    public function test_batch_does_not_recalculate_unrelated_products(): void
    {
        $this->mock(DatabaseBackup::class, fn (MockInterface $backup) => $backup->shouldReceive('create')->twice()->andReturn('backup.sql.gz'));
        $id = $this->importId($this->upload());
        $this->postJson('/admin/method/supplier-import?method=apply', ['import' => $id])->assertOk();
        $offer = $this->supplier->offers()->whereNull('product_id')->firstOrFail();
        $other = Product::query()->create(['name' => 'Другой товар', 'slug' => 'unrelated', 'price' => 1234, 'quantity' => 0, 'supplier_id' => $this->supplier->id, 'supplier_price' => 1234, 'supplier_quantity' => 7]);
        $this->postJson('/admin/method/supplier-import?method=linkOffer', [
            'import' => $id, 'offer_id' => $offer->id, 'product_id' => $this->product->id,
        ])->assertOk();

        $this->postJson('/admin/method/supplier-import?method=applyDraftLinks', ['import' => $id])->assertOk();

        $this->assertSame(1234.0, (float) $other->fresh()->supplier_price);
        $this->assertSame($this->product->id, $offer->fresh()->product_id);
    }

    public function test_unlinked_positions_after_first_two_hundred_are_accessible(): void
    {
        $csv = '';
        for ($number = 1; $number <= 201; $number++) {
            $csv .= "Позиция {$number};100;200\n";
        }
        $id = $this->importId($this->postJson('/admin/method/supplier-import?method=upload', [
            'supplier_id' => $this->supplier->id,
            'file' => UploadedFile::fake()->createWithContent('price.csv', $csv),
        ]));

        $this->get('/admin/page/supplier-import?import='.$id.'&unlinked_page=2')
            ->assertSee('Позиция 201')->assertDontSee('Позиция 199');
    }

    public function test_legacy_applied_marker_still_blocks_reapplication(): void
    {
        $this->mock(DatabaseBackup::class, fn (MockInterface $backup) => $backup->shouldNotReceive('create'));
        $id = $this->importId($this->upload());
        $meta = app(SupplierImportFiles::class)->meta($id);
        Storage::disk('local')->put('supplier-imports/'.$id.'.json', json_encode([...$meta, 'applied_at' => now()->toIso8601String()], JSON_THROW_ON_ERROR));

        $this->postJson('/admin/method/supplier-import?method=apply', ['import' => $id])
            ->assertOk()->assertJsonPath('messageType', 'error');

        $this->assertSame(0, $this->supplier->offers()->count());
    }

    public function test_existing_session_draft_is_preserved_and_migrated(): void
    {
        $this->mock(DatabaseBackup::class, fn (MockInterface $backup) => $backup->shouldReceive('create')->once()->andReturn('backup.sql.gz'));
        $id = $this->importId($this->upload());
        $this->postJson('/admin/method/supplier-import?method=apply', ['import' => $id])->assertOk();
        $offer = $this->supplier->offers()->whereNull('product_id')->firstOrFail();
        $this->withSession(['supplier_link_drafts' => [$id => [$offer->id => $this->product->id]]]);

        $this->get('/admin/page/supplier-import?import='.$id)->assertSee('На проверке (1)');
        $this->app['session']->flush();

        $this->get('/admin/page/supplier-import?import='.$id)->assertSee('На проверке (1)');
        $this->assertNull($offer->fresh()->product_id);
    }

    public function test_other_admin_cannot_apply_or_remove_my_draft(): void
    {
        $this->mock(DatabaseBackup::class, fn (MockInterface $backup) => $backup->shouldReceive('create')->once()->andReturn('backup.sql.gz'));
        $admin = auth('moonshine')->user();
        $id = $this->importId($this->upload());
        $this->postJson('/admin/method/supplier-import?method=apply', ['import' => $id])->assertOk();
        $offer = $this->supplier->offers()->whereNull('product_id')->firstOrFail();
        $this->postJson('/admin/method/supplier-import?method=linkOffer', [
            'import' => $id, 'offer_id' => $offer->id, 'product_id' => $this->product->id,
        ])->assertOk();
        $other = MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'other@example.com', 'name' => 'Other', 'password' => bcrypt('secret'),
        ]);
        $this->app['session']->flush();
        $this->actingAs($other, 'moonshine');

        $this->get('/admin/page/supplier-import?import='.$id)->assertDontSee('На проверке (1)');
        $this->postJson('/admin/method/supplier-import?method=applyDraftLinks', ['import' => $id])->assertUnprocessable()->assertJsonValidationErrors('import');
        $this->postJson('/admin/method/supplier-import?method=removeDraftLink', ['import' => $id, 'offer_id' => $offer->id])->assertOk();

        $this->app['session']->flush();
        $this->actingAs($admin, 'moonshine');
        $this->get('/admin/page/supplier-import?import='.$id)->assertSee('На проверке (1)');
        $this->assertNull($offer->fresh()->product_id);
    }

    public function test_deleted_offer_prevents_partial_batch_application(): void
    {
        $this->mock(DatabaseBackup::class, fn (MockInterface $backup) => $backup->shouldReceive('create')->once()->andReturn('backup.sql.gz'));
        $id = $this->importId($this->postJson('/admin/method/supplier-import?method=upload', [
            'supplier_id' => $this->supplier->id,
            'file' => UploadedFile::fake()->createWithContent('price.csv', "Первый;100;200\nВторой;100;300\n"),
        ]));
        $this->postJson('/admin/method/supplier-import?method=apply', ['import' => $id])->assertOk();
        $offers = $this->supplier->offers()->orderBy('id')->get();
        foreach ($offers as $offer) {
            $this->postJson('/admin/method/supplier-import?method=linkOffer', [
                'import' => $id, 'offer_id' => $offer->id, 'product_id' => $this->product->id,
            ])->assertOk();
        }
        $offers[1]->delete();

        $this->postJson('/admin/method/supplier-import?method=applyDraftLinks', ['import' => $id])->assertJsonValidationErrors('import');

        $this->assertNull($offers[0]->fresh()->product_id);
        $this->get('/admin/page/supplier-import?import='.$id)->assertSee('Предложение удалено')->assertSee('На проверке (2)');
    }

    public function test_selection_and_removal_preserve_the_current_page(): void
    {
        $this->mock(DatabaseBackup::class, fn (MockInterface $backup) => $backup->shouldReceive('create')->once()->andReturn('backup.sql.gz'));
        $id = $this->importId($this->upload());
        $this->postJson('/admin/method/supplier-import?method=apply', ['import' => $id])->assertOk();
        $offer = $this->supplier->offers()->whereNull('product_id')->firstOrFail();

        $this->postJson('/admin/method/supplier-import?method=linkOffer', [
            'import' => $id, 'offer_id' => $offer->id, 'product_id' => $this->product->id, 'unlinked_page' => 2,
        ])->assertOk()->assertJsonPath('redirect', url('/admin/page/supplier-import').'?import='.$id.'&unlinked_page=2');
        $this->postJson('/admin/method/supplier-import?method=removeDraftLink', [
            'import' => $id, 'offer_id' => $offer->id, 'unlinked_page' => 2,
        ])->assertOk()->assertJsonPath('redirect', url('/admin/page/supplier-import').'?import='.$id.'&unlinked_page=2');

        $this->assertNull($offer->fresh()->product_id);
    }

    public function test_previously_applied_offer_can_be_reviewed_again_after_unlinking(): void
    {
        $this->mock(DatabaseBackup::class, fn (MockInterface $backup) => $backup->shouldReceive('create')->times(3)->andReturn('backup.sql.gz'));
        $id = $this->importId($this->upload());
        $this->postJson('/admin/method/supplier-import?method=apply', ['import' => $id])->assertOk();
        $offer = $this->supplier->offers()->whereNull('product_id')->firstOrFail();
        $selection = ['import' => $id, 'offer_id' => $offer->id, 'product_id' => $this->product->id];
        $this->postJson('/admin/method/supplier-import?method=linkOffer', $selection)->assertOk();
        $this->postJson('/admin/method/supplier-import?method=applyDraftLinks', ['import' => $id])->assertOk();
        $offer->refresh()->update(['product_id' => null]);

        $this->postJson('/admin/method/supplier-import?method=linkOffer', $selection)->assertOk();

        $this->get('/admin/page/supplier-import?import='.$id)->assertSee('На проверке (1)');
        $this->assertNull($offer->fresh()->product_id);
        $this->postJson('/admin/method/supplier-import?method=applyDraftLinks', ['import' => $id])->assertOk();
        $this->assertSame($this->product->id, $offer->fresh()->product_id);
    }

    public function test_recent_uploads_can_be_sorted_by_date_and_file_name(): void
    {
        $uploads = [
            '20261031-120000-aaaaaa' => ['original_name' => 'z-price.csv', 'uploaded_at' => '2026-10-31T12:00:00+05:00'],
            '20261101-120000-bbbbbb' => ['original_name' => 'a-price.csv', 'uploaded_at' => '2026-11-01T12:00:00+05:00'],
        ];
        foreach ($uploads as $id => $metadata) {
            Storage::disk('local')->put('supplier-imports/'.$id.'.json', json_encode([...$metadata, 'supplier_id' => $this->supplier->id], JSON_THROW_ON_ERROR));
        }

        $this->get('/admin/page/supplier-import')->assertOk()
            ->assertSeeInOrder(['a-price.csv', 'z-price.csv'])
            ->assertSee('sort=uploaded', false)
            ->assertSee('sort=name', false);
        $this->get('/admin/page/supplier-import?sort=uploaded')->assertOk()
            ->assertSeeInOrder(['z-price.csv', 'a-price.csv']);
        $this->get('/admin/page/supplier-import?sort=-name')->assertOk()
            ->assertSeeInOrder(['z-price.csv', 'a-price.csv']);
        $this->get('/admin/page/supplier-import?sort=invalid')->assertOk()
            ->assertSeeInOrder(['a-price.csv', 'z-price.csv']);
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
            ->assertSee('К загрузкам прайсов')
            ->assertSee('href="'.url('/admin/page/supplier-import').'"', false)
            ->assertDontSee('Загрузить и проверить')
            ->assertDontSee('Последние загрузки')
            ->assertSee('data-resizable', false);

        $this->get('/admin/page/supplier-import')
            ->assertOk()
            ->assertSee('Загрузить и проверить')
            ->assertSee('Последние загрузки')
            ->assertDontSee('К загрузкам прайсов');

        $this->assertNull($this->product->fresh()->supplier_id);
        $this->assertNotNull(app(SupplierImportFiles::class)->path($id));
    }

    public function test_apply_backs_up_and_puts_product_on_order(): void
    {
        $this->mock(DatabaseBackup::class, fn (MockInterface $backup) => $backup->shouldReceive('create')->once()->with('before-supplier-import')->andReturn('backup.sql.gz'));
        $id = $this->importId($this->upload());

        $this->postJson('/admin/method/supplier-import?'.http_build_query(['method' => 'apply', 'import' => $id]))->assertOk();

        $this->assertSame($this->supplier->id, $this->supplier->offers()->first()->supplier_id);
        $this->get("/admin/page/supplier-import?import={$id}")->assertSee('Прайс применён')->assertDontSee('Применить этот файл повторно')->assertDontSee('Применить к базе');
        $meta = app(SupplierImportFiles::class)->meta($id);
        $this->assertSame('Admin', $meta['applied_by_name']);
        $this->assertSame(auth('moonshine')->id(), $meta['applied_by_id']);
        $this->get('/admin/page/supplier-import')->assertSee('Кто применил')->assertSee('Admin');
    }

    public function test_applied_price_cannot_be_applied_again(): void
    {
        $this->mock(DatabaseBackup::class, fn (MockInterface $backup) => $backup->shouldReceive('create')->once()->with('before-supplier-import')->andReturn('backup.sql.gz'));
        $id = $this->importId($this->upload());
        $url = '/admin/method/supplier-import?method=apply';
        $this->postJson($url, ['import' => $id])->assertOk();
        $offer = $this->supplier->offers()->firstOrFail();
        $offer->update(['cost' => 1234]);

        $this->postJson($url, ['import' => $id])->assertOk()
            ->assertJsonPath('messageType', 'error')
            ->assertJsonPath('message', 'Этот прайс уже применён. Для обновления загрузите новый файл.');
        $this->assertSame(1234.0, (float) $offer->fresh()->cost);
        $this->get("/admin/page/supplier-import?import={$id}")
            ->assertSee('Прайс применён')->assertDontSee('Применить к базе')->assertDontSee('Применить этот файл повторно');
    }

    public function test_unrecognized_price_shows_warning_and_cannot_be_applied(): void
    {
        $offer = SupplierOffer::factory()->create(['supplier_id' => $this->supplier->id, 'product_id' => $this->product->id, 'quantity' => 8, 'in_price' => true]);
        $this->mock(DatabaseBackup::class, fn (MockInterface $backup) => $backup->shouldReceive('create')->once()->andReturn('backup.sql.gz'));
        $response = $this->postJson('/admin/method/supplier-import?method=upload', [
            'supplier_id' => $this->supplier->id,
            'file' => UploadedFile::fake()->createWithContent('price.csv', "Название;не цена;не цена\n"),
        ]);
        $id = $this->importId($response);
        $this->get("/admin/page/supplier-import?import={$id}")
            ->assertOk()->assertSee('Не удалось распознать ни одной позиции')
            ->assertSee('Применение заблокировано')->assertSee('исключено позиций: 1')
            ->assertSee('alert-error', false)->assertDontSee('Применить к базе')
            ->assertDontSee('Применить этот файл повторно');
        $this->postJson('/admin/method/supplier-import?method=apply', ['import' => $id])
            ->assertOk()->assertJsonPath('messageType', 'error');
        $this->assertTrue($offer->fresh()->in_price);
        $this->assertSame(8, $offer->fresh()->quantity);
        $this->assertArrayNotHasKey('applied_at', app(SupplierImportFiles::class)->meta($id));
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
        $this->get('/admin/page/supplier-import?import=../../.env')->assertOk()
            ->assertSee('Загрузка не найдена')
            ->assertSee('К загрузкам прайсов')
            ->assertDontSee('Загрузить и проверить')
            ->assertDontSee('Применить к базе');
    }

    public function test_linking_saved_offer_reprices_but_does_not_activate_product(): void
    {
        $this->mock(DatabaseBackup::class, fn (MockInterface $backup) => $backup->shouldReceive('create')->twice()->andReturn('backup.sql.gz'));
        $id = $this->importId($this->upload());
        $this->get("/admin/page/supplier-import?import={$id}")->assertDontSee('Выбрать товар');
        $this->postJson('/admin/method/supplier-import?method=apply', ['import' => $id])->assertOk();
        $offer = $this->supplier->offers()->whereNull('product_id')->firstOrFail();
        $this->product->update(['status' => false]);

        $this->get("/admin/page/supplier-import?import={$id}")->assertOk()->assertSee('Выбрать товар')->assertSee('Добавить на проверку');
        $this->postJson('/admin/method/supplier-import?method=linkOffer', [
            'import' => $id, 'offer_id' => $offer->id, 'product_id' => $this->product->id,
        ])->assertOk()->assertJsonPath('redirect', url('/admin/page/supplier-import').'?import='.$id);

        $this->assertNull($offer->fresh()->product_id);
        $this->get("/admin/page/supplier-import?import={$id}")
            ->assertSee('На проверке (1)')->assertSee('Применить все привязки')
            ->assertSee('Открыть карточку товара')->assertDontSee('Карточка</th>', false)
            ->assertSee('supplier-product-detail-'.$offer->id, false)
            ->assertSee('supplier-offer-detail-'.$offer->id, false)
            ->assertSee('Открыть позицию поставщика')->assertSee('АКБ Дриллинг')
            ->assertSee('Закупочная цена')->assertSee('200 ₽')->assertSee('Остаток у поставщика')->assertSee('Не указан')
            ->assertSee('В актуальном прайсе')->assertSee('Товар на сайте')->assertSee('Не привязан')
            ->assertSee('table-divider', false)
            ->assertSee('Карточка товара')
            ->assertSee(app(ProductResource::class)->getDetailPageUrl($this->product->id, fragment: 'crud-detail'), false);
        $this->get(app(ProductResource::class)->getDetailPageUrl($this->product->id, fragment: 'crud-detail'))
            ->assertOk()->assertSee('Аккумулятор TOPLA Energy 60')->assertSee('Характеристики');
        $this->postJson('/admin/method/supplier-import?method=applyDraftLinks', ['import' => $id])->assertOk();

        $this->assertSame($this->product->id, $offer->fresh()->product_id);
        $this->assertFalse($this->product->fresh()->status);
        $this->assertSame(250.0, (float) $this->product->fresh()->supplier_price);
        $this->assertSame(9000.0, (float) $this->product->fresh()->price);
        $this->get("/admin/page/supplier-import?import={$id}")->assertDontSee('Выбрать товар');
        $offer->update(['cost' => 300]);
        $this->get("/admin/page/supplier-import?import={$id}")->assertOk()->assertSee('Изменилась закупка (1)');

        $this->postJson('/admin/method/supplier-import?method=linkOffer', [
            'import' => $id, 'offer_id' => $offer->id, 'product_id' => $this->product->id,
        ])->assertJsonValidationErrors('offer_id');
    }

    public function test_draft_can_be_replaced_removed_and_survives_reload(): void
    {
        $this->mock(DatabaseBackup::class, fn (MockInterface $backup) => $backup->shouldReceive('create')->once()->andReturn('backup.sql.gz'));
        $id = $this->importId($this->upload());
        $this->postJson('/admin/method/supplier-import?method=apply', ['import' => $id])->assertOk();
        $offer = $this->supplier->offers()->whereNull('product_id')->firstOrFail();
        $other = Product::query()->create(['name' => 'Другой выбор', 'slug' => 'other-choice', 'price' => 100, 'quantity' => 0, 'status' => true]);
        $url = '/admin/method/supplier-import?method=linkOffer';
        $this->postJson($url, ['import' => $id, 'offer_id' => $offer->id, 'product_id' => $this->product->id])->assertOk();
        $this->postJson($url, ['import' => $id, 'offer_id' => $offer->id, 'product_id' => $other->id])->assertOk();
        $page = $this->get("/admin/page/supplier-import?import={$id}");
        $page->assertSee('На проверке (1)')->assertSee('Другой выбор')->assertSee('Изменить выбор');
        $dom = new \DOMDocument;
        @$dom->loadHTML($page->getContent());
        $buttons = (new \DOMXPath($dom))->query('//a[contains(@href, "removeDraftLink")]');
        $this->assertSame(1, $buttons->length);
        $this->assertSame('post', $buttons->item(0)->getAttribute('data-async-method'));
        $this->postJson('/admin/method/supplier-import?method=removeDraftLink', ['import' => $id, 'offer_id' => $offer->id])->assertOk();
        $this->get("/admin/page/supplier-import?import={$id}")->assertDontSee('На проверке (1)')
            ->assertDontSee('Изменить выбор')->assertSee('Выбрать товар');
        $this->assertNull($offer->fresh()->product_id);
    }

    public function test_batch_applies_multiple_links_and_clears_queue(): void
    {
        $this->mock(DatabaseBackup::class, fn (MockInterface $backup) => $backup->shouldReceive('create')->twice()->andReturn('backup.sql.gz'));
        $response = $this->postJson('/admin/method/supplier-import?method=upload', [
            'supplier_id' => $this->supplier->id,
            'file' => UploadedFile::fake()->createWithContent('price.csv', "Первый;100;200\nВторой;100;300\n"),
        ]);
        $id = $this->importId($response);
        $this->postJson('/admin/method/supplier-import?method=apply', ['import' => $id])->assertOk();
        $offers = $this->supplier->offers()->orderBy('id')->get();
        $other = Product::query()->create(['name' => 'Второй товар сайта', 'slug' => 'second-site-product', 'price' => 500, 'quantity' => 0, 'status' => true]);
        foreach ([[$offers[0], $this->product], [$offers[1], $other]] as [$offer, $product]) {
            $this->postJson('/admin/method/supplier-import?method=linkOffer', ['import' => $id, 'offer_id' => $offer->id, 'product_id' => $product->id])->assertOk();
        }
        $this->assertNull($offers[0]->fresh()->product_id);
        $this->assertNull($offers[1]->fresh()->product_id);
        $this->postJson('/admin/method/supplier-import?method=applyDraftLinks', ['import' => $id])->assertOk();
        $this->assertSame($this->product->id, $offers[0]->fresh()->product_id);
        $this->assertSame($other->id, $offers[1]->fresh()->product_id);
        $this->assertSame(250.0, (float) $this->product->fresh()->supplier_price);
        $this->assertSame(400.0, (float) $other->fresh()->supplier_price);
        $this->get("/admin/page/supplier-import?import={$id}")->assertDontSee('На проверке (2)');
        $this->postJson('/admin/method/supplier-import?method=applyDraftLinks', ['import' => $id])->assertJsonValidationErrors('import');
    }

    public function test_backup_failure_preserves_draft_and_does_not_link(): void
    {
        $backup = $this->mock(DatabaseBackup::class);
        $backup->shouldReceive('create')->once()->with('before-supplier-import')->andReturn('backup.sql.gz');
        $backup->shouldReceive('create')->once()->with('before-supplier-links')->andThrow(new \RuntimeException('ошибка копирования'));
        $id = $this->importId($this->upload());
        $this->postJson('/admin/method/supplier-import?method=apply', ['import' => $id])->assertOk();
        $offer = $this->supplier->offers()->whereNull('product_id')->firstOrFail();
        $this->postJson('/admin/method/supplier-import?method=linkOffer', ['import' => $id, 'offer_id' => $offer->id, 'product_id' => $this->product->id])->assertOk();
        $this->postJson('/admin/method/supplier-import?method=applyDraftLinks', ['import' => $id])->assertOk()->assertJsonPath('messageType', 'error');
        $this->assertNull($offer->fresh()->product_id);
        $this->get("/admin/page/supplier-import?import={$id}")->assertSee('На проверке (1)');
    }

    public function test_batch_is_atomic_if_a_draft_becomes_stale(): void
    {
        $this->mock(DatabaseBackup::class, fn (MockInterface $backup) => $backup->shouldReceive('create')->once()->andReturn('backup.sql.gz'));
        $csv = "Первый;100;200\nВторой;100;200\n";
        $response = $this->postJson('/admin/method/supplier-import?method=upload', [
            'supplier_id' => $this->supplier->id, 'file' => UploadedFile::fake()->createWithContent('price.csv', $csv),
        ]);
        $id = $this->importId($response);
        $this->postJson('/admin/method/supplier-import?method=apply', ['import' => $id])->assertOk();
        $offers = $this->supplier->offers()->orderBy('id')->get();
        foreach ($offers as $offer) {
            $this->postJson('/admin/method/supplier-import?method=linkOffer', ['import' => $id, 'offer_id' => $offer->id, 'product_id' => $this->product->id])->assertOk();
        }
        $offers[1]->update(['in_price' => false]);
        $this->postJson('/admin/method/supplier-import?method=applyDraftLinks', ['import' => $id])->assertJsonValidationErrors('import');
        $this->assertNull($offers[0]->fresh()->product_id);
        $this->assertNull($offers[1]->fresh()->product_id);
        $this->get("/admin/page/supplier-import?import={$id}")->assertSee('На проверке (2)');
    }

    public function test_product_search_includes_inactive_and_excludes_deleted_products(): void
    {
        $this->product->update(['status' => false]);
        $deleted = Product::query()->create(['name' => 'TOPLA удалённый', 'slug' => 'deleted-topla', 'price' => 100, 'quantity' => 0, 'status' => true]);
        $deleted->delete();
        $this->getJson('/admin/method/supplier-import?method=searchProducts&query=TOPLA')
            ->assertOk()->assertJsonCount(1, 'options')->assertJsonPath('options.0.value', (string) $this->product->id);
        $this->getJson('/admin/method/supplier-import?method=searchProducts&query='.$this->product->id)
            ->assertOk()->assertJsonPath('options.0.value', (string) $this->product->id);
        $this->getJson('/admin/method/supplier-import?method=searchProducts')->assertJsonCount(0, 'options');
    }

    public function test_linking_rejects_preview_foreign_offers_and_deleted_products(): void
    {
        $id = $this->importId($this->upload());
        $offer = SupplierOffer::factory()->create(['supplier_id' => $this->supplier->id, 'product_id' => null]);
        $payload = ['import' => $id, 'offer_id' => $offer->id, 'product_id' => $this->product->id];
        $url = '/admin/method/supplier-import?method=linkOffer';
        $this->postJson($url, $payload)->assertStatus(422);
        app(SupplierImportFiles::class)->markApplied($id, [], auth('moonshine')->id(), 'Admin');
        $this->postJson($url, $payload)->assertStatus(422);
        $offer->update(['supplier_code' => 'новый товар']);
        $other = Supplier::factory()->create();
        $offer->update(['supplier_id' => $other->id]);
        $this->postJson($url, $payload)->assertStatus(422);
        $offer->update(['supplier_id' => $this->supplier->id]);
        $this->product->delete();
        $this->postJson($url, $payload)->assertStatus(422);
        $this->assertNull($offer->fresh()->product_id);
        $this->getJson($url.'&'.http_build_query($payload))->assertStatus(405);
    }

    public function test_guest_cannot_search_or_link_offers(): void
    {
        auth('moonshine')->logout();
        $this->get('/admin/method/supplier-import?method=searchProducts&query=TOPLA')->assertRedirect();
        $this->post('/admin/method/supplier-import?method=linkOffer', [])->assertRedirect();
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
