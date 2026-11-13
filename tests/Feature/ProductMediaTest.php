<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Catalog\ProductMedia;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use League\Flysystem\UnableToWriteFile;
use Tests\TestCase;

class ProductMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_mixed_urls_and_placeholder_do_not_require_storage_requests(): void
    {
        config(['filesystems.product_upload_disk' => 'media', 'filesystems.product_legacy_disk' => 'public']);
        Storage::fake('public', ['url' => 'https://shop.example/storage']);
        Storage::fake('media', ['url' => 'https://media.example']);

        $this->assertSame('https://media.example/media/products/new.jpg', ProductMedia::url('media/products/new.jpg'));
        $this->assertSame('https://shop.example/storage/catalog/old.jpg', ProductMedia::url('catalog/old.jpg'));
        $this->assertSame('https://shop.example/storage/no_image.png', ProductMedia::url(null));

        config(['filesystems.product_upload_disk' => 'public', 'filesystems.product_legacy_disk' => 'media']);

        $this->assertSame('https://media.example/catalog/old.jpg', ProductMedia::url('catalog/old.jpg'));
        $this->assertSame('https://media.example/media/products/new.jpg', ProductMedia::url('media/products/new.jpg'));
        $this->assertSame('https://shop.example/storage/no_image.png', ProductMedia::url('no_image.png'));
    }

    public function test_paths_cannot_escape_the_media_storage(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ProductMedia::url('catalog/../../private.txt');
    }

    public function test_product_page_and_seo_use_public_media_urls(): void
    {
        Storage::fake('media', ['url' => 'https://media.example']);
        $product = $this->product('media/products/main.jpg');
        $product->images()->create(['path' => 'media/products/extra.jpg', 'sort_order' => 1]);

        $this->get(route('product.show', $product))
            ->assertOk()
            ->assertSee('https://media.example/media/products/main.jpg', false)
            ->assertSee('https://media.example/media/products/extra.jpg', false)
            ->assertSee('<meta property="og:image" content="https://media.example/media/products/main.jpg">', false);
    }

    public function test_copy_preserves_paths_shared_references_and_sources_and_is_repeatable(): void
    {
        $local = Storage::fake('public');
        $remote = Storage::fake('media');
        $product = $this->product('catalog/старое фото.jpg');
        $product->images()->create(['path' => $product->image, 'sort_order' => 1]);
        $local->put($product->image, 'original image bytes');

        $this->artisan('products:copy-media')->expectsOutputToContain('к переносу: 1')->assertSuccessful();
        $remote->assertDirectoryEmpty('/');

        $this->artisan('products:copy-media', ['--force' => true])->expectsOutputToContain('Копий: 1')->assertSuccessful();
        $this->assertSame('original image bytes', $remote->get($product->image));
        $local->assertExists($product->image);
        $this->assertSame('catalog/старое фото.jpg', $product->fresh()->image);

        $this->artisan('products:copy-media', ['--force' => true])->expectsOutputToContain('совпадают: 1')->assertSuccessful();
    }

    public function test_copy_refuses_conflicting_objects_even_when_sizes_match(): void
    {
        $local = Storage::fake('public');
        $remote = Storage::fake('media');
        $product = $this->product('catalog/conflict.jpg');
        $local->put($product->image, 'first');
        $remote->put($product->image, 'other');

        $this->artisan('products:copy-media', ['--force' => true])->expectsOutputToContain('ошибок: 1')->assertFailed();

        $this->assertSame('other', $remote->get($product->image));
        $this->assertSame('first', $local->get($product->image));
    }

    public function test_copy_reports_missing_sources_without_changing_product(): void
    {
        Storage::fake('public');
        $remote = Storage::fake('media');
        $product = $this->product('catalog/missing.jpg');

        $this->artisan('products:copy-media', ['--force' => true])->expectsOutputToContain('ошибок: 1')->assertFailed();

        $remote->assertDirectoryEmpty('/');
        $this->assertSame('catalog/missing.jpg', $product->fresh()->image);
    }

    public function test_copy_includes_soft_deleted_products_and_leaves_static_files_local(): void
    {
        $local = Storage::fake('public');
        $remote = Storage::fake('media');
        $product = $this->product('catalog/deleted.jpg');
        $product->delete();
        $local->put('catalog/deleted.jpg', 'product image');
        $local->put('logo.png', 'static logo');

        $this->artisan('products:copy-media', ['--force' => true])->assertSuccessful();

        $remote->assertExists('catalog/deleted.jpg');
        $remote->assertMissing('logo.png');
        $local->assertExists('logo.png');
    }

    public function test_copy_failure_keeps_source_and_does_not_expose_upstream_details(): void
    {
        $local = Storage::fake('public');
        $product = $this->product('catalog/photo.jpg');
        $local->put($product->image, 'original');
        $remote = \Mockery::mock(FilesystemAdapter::class);
        $remote->shouldReceive('exists')->once()->andReturn(false);
        $remote->shouldReceive('put')->once()->andThrow(new UnableToWriteFile('sensitive upstream details'));
        Storage::set('media', $remote);

        $this->artisan('products:copy-media', ['--force' => true])
            ->expectsOutputToContain('ошибок: 1')->doesntExpectOutputToContain('sensitive upstream details')->assertFailed();

        $local->assertExists($product->image);
    }

    private function product(string $image): Product
    {
        return Product::query()->create(['name' => 'Media product', 'slug' => 'media-product', 'price' => 100, 'status' => true, 'image' => $image]);
    }
}
