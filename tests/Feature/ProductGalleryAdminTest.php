<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class ProductGalleryAdminTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private ProductImage $existing;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $admin = MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($admin, 'moonshine');

        $this->product = Product::query()->create([
            'name' => 'TITAN 60Ah О.П.',
            'slug' => 'titan-60ah-op',
            'price' => 5200,
            'image' => 'catalog/akb/titan.jpg',
        ]);

        $this->existing = $this->product->images()->create(['path' => 'catalog/productimg/extra.jpg', 'sort_order' => 1]);
    }

    public function test_photo_tab_shows_main_and_extra_photos(): void
    {
        $this->get("/admin/resource/product-resource/product-form-page/{$this->product->id}")
            ->assertOk()
            ->assertSee('Дополнительные фото')
            // Превью — по пути из базы: импортированные фото лежат в разных
            // папках, к ним нельзя приписывать папку новых загрузок.
            ->assertSee('src="'.Storage::disk('public')->url('catalog/akb/titan.jpg').'"', false)
            ->assertSee('src="'.Storage::disk('public')->url('catalog/productimg/extra.jpg').'"', false)
            ->assertDontSee('catalog/products/catalog/', false);
    }

    public function test_saving_keeps_existing_photos_and_adds_uploaded_one(): void
    {
        $this->saveProduct([
            ['id' => $this->existing->id, 'hidden_path' => 'catalog/productimg/extra.jpg', 'sort_order' => 2],
            ['path' => UploadedFile::fake()->image('new.jpg'), 'sort_order' => 1],
        ])->assertRedirect();

        $this->assertSame('catalog/akb/titan.jpg', $this->product->refresh()->image);

        $images = $this->product->images()->get();
        $this->assertCount(2, $images);

        $uploaded = $images->firstWhere('id', '!=', $this->existing->id);
        $this->assertStringStartsWith('catalog/products/', $uploaded->path);
        Storage::disk('public')->assertExists($uploaded->path);

        // Порядок: новое фото (1) раньше старого (2).
        $this->assertSame($uploaded->id, $images->first()->id);
    }

    public function test_removed_row_deletes_record_but_keeps_shared_file(): void
    {
        Storage::disk('public')->put('catalog/productimg/extra.jpg', 'jpeg');

        $this->saveProduct([])->assertRedirect();

        $this->assertSame(0, $this->product->images()->count());
        // Файл может использоваться другими товарами — с диска не удаляем.
        Storage::disk('public')->assertExists('catalog/productimg/extra.jpg');
    }

    public function test_media_upload_keeps_legacy_preview_and_stores_new_gallery_remotely(): void
    {
        config(['filesystems.product_upload_disk' => 'media']);
        $remote = Storage::fake('media', ['url' => 'https://media.example']);

        $this->saveProduct([
            ['id' => $this->existing->id, 'hidden_path' => $this->existing->path, 'sort_order' => 1],
            ['path' => UploadedFile::fake()->image('new.jpg'), 'sort_order' => 2],
        ])->assertRedirect();

        $uploaded = $this->product->images()->whereKeyNot($this->existing->id)->firstOrFail();
        $this->assertStringStartsWith('media/products/', $uploaded->path);
        $remote->assertExists($uploaded->path);
        Storage::disk('public')->assertMissing($uploaded->path);
        $this->get("/admin/resource/product-resource/product-form-page/{$this->product->id}")
            ->assertSee('https://media.example/'.$uploaded->path, false)
            ->assertSee(Storage::disk('public')->url($this->existing->path), false);
    }

    public function test_replacing_main_photo_uses_media_and_preserves_shared_original(): void
    {
        config(['filesystems.product_upload_disk' => 'media']);
        $remote = Storage::fake('media');
        Storage::disk('public')->put($this->product->image, 'shared original');

        $this->saveProduct([], ['image' => UploadedFile::fake()->image('replacement.png')])->assertRedirect();

        $this->assertStringStartsWith('media/products/', $this->product->refresh()->image);
        $remote->assertExists($this->product->image);
        Storage::disk('public')->assertExists('catalog/akb/titan.jpg');
    }

    public function test_invalid_gallery_upload_is_rejected_before_changing_records(): void
    {
        config(['filesystems.product_upload_disk' => 'media']);
        $remote = Storage::fake('media');

        $this->saveProduct([['path' => UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml')]])
            ->assertSessionHasErrors('images.0.path', errorBag: 'product-resource');

        $this->assertSame(1, $this->product->images()->count());
        $this->assertSame('catalog/akb/titan.jpg', $this->product->fresh()->image);
        $remote->assertDirectoryEmpty('/');
    }

    public function test_oversized_main_photo_is_rejected(): void
    {
        $this->saveProduct([], ['image' => UploadedFile::fake()->image('large.jpg')->size(10241)])
            ->assertSessionHasErrors('image', errorBag: 'product-resource');

        $this->assertSame('catalog/akb/titan.jpg', $this->product->fresh()->image);
    }

    public function test_failed_media_write_keeps_main_photo_and_shows_safe_error(): void
    {
        config(['filesystems.product_upload_disk' => 'media']);
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andThrow(new \RuntimeException('sensitive upstream details'));
        Storage::set('media', $disk);

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->saveProduct([], ['image' => UploadedFile::fake()->image('new.jpg')])
            ->assertUnprocessable()
            ->assertJsonPath('errors.image.0', 'Не удалось сохранить изображение. Попробуйте загрузить его ещё раз.')
            ->assertDontSee('sensitive upstream details', false);

        $this->assertSame('catalog/akb/titan.jpg', $this->product->fresh()->image);
        $this->assertSame(1, $this->product->images()->count());
    }

    public function test_failed_gallery_write_rolls_back_product_changes(): void
    {
        config(['filesystems.product_upload_disk' => 'media']);
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $attempt = 0;
        $disk->shouldReceive('putFileAs')->twice()->andReturnUsing(function () use (&$attempt): string|false {
            return ++$attempt === 1 ? 'media/products/new.jpg' : false;
        });
        Storage::set('media', $disk);

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->saveProduct([['path' => UploadedFile::fake()->image('new.jpg')]], ['name' => 'Changed name'])
            ->assertUnprocessable();

        $this->assertSame('TITAN 60Ah О.П.', $this->product->fresh()->name);
        $this->assertSame(1, $this->product->images()->count());
    }

    /**
     * @param  list<array<string, mixed>>  $images
     */
    private function saveProduct(array $images, array $overrides = []): TestResponse
    {
        // Как браузер: multipart POST с подменой метода — так форма MoonShine
        // и отправляет файлы.
        return $this->post("/admin/resource/product-resource/crud/{$this->product->id}", [
            '_method' => 'PUT',
            'name' => $this->product->name,
            'slug' => $this->product->slug,
            'price' => 5200,
            'quantity' => 0,
            'status' => 1,
            'hidden_image' => 'catalog/akb/titan.jpg',
            'images' => $images,
            ...$overrides,
        ]);
    }
}
