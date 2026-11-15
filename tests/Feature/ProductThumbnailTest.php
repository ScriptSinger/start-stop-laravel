<?php

namespace Tests\Feature;

use App\Jobs\GenerateProductThumbnail;
use App\Models\Product;
use App\Services\Catalog\ProductMedia;
use App\Services\Catalog\ProductThumbnail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ProductThumbnailTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_webp_preview_without_upscaling(): void
    {
        $image = imagecreatetruecolor(880, 440);
        ob_start();
        imagepng($image);
        $original = ob_get_clean();
        imagedestroy($image);

        $thumbnail = app(ProductThumbnail::class)->make($original);
        $info = getimagesizefromstring($thumbnail);

        $this->assertSame(440, $info[0]);
        $this->assertSame(220, $info[1]);
        $this->assertSame(IMAGETYPE_WEBP, $info[2]);
        $this->assertSame(220, getimagesizefromstring(app(ProductThumbnail::class)->make($thumbnail))[1]);
    }

    public function test_it_rejects_invalid_image_data(): void
    {
        $this->expectException(RuntimeException::class);

        app(ProductThumbnail::class)->make('not an image');
    }

    public function test_remote_original_produces_local_preview_and_refreshes_when_replaced(): void
    {
        Queue::fake();
        $local = Storage::fake('public', ['url' => 'https://shop.example/storage']);
        $remote = Storage::fake('media', ['url' => 'https://media.example']);
        $path = 'media/products/test.png';
        $product = Product::query()->create(['name' => 'Battery', 'slug' => 'battery', 'price' => 100, 'status' => true, 'image' => $path]);
        $first = $this->imageBytes(880, 440);
        $remote->put($path, $first);

        (new GenerateProductThumbnail($product->id, $path))->handle(app(ProductThumbnail::class));
        $preview = $product->fresh()->thumbnail_path;
        $local->assertExists($preview);
        $this->assertSame($first, $remote->get($path));
        $this->assertSame('https://shop.example/storage/'.$preview, ProductMedia::thumbnailUrl($path, $preview));

        $remote->put($path, $this->imageBytes(200, 100));
        (new GenerateProductThumbnail($product->id, $path))->handle(app(ProductThumbnail::class));
        $this->assertNotSame($preview, $product->fresh()->thumbnail_path);

        $product->update(['image' => 'media/products/replacement.png']);
        $this->assertSame('https://media.example/media/products/replacement.png', ProductMedia::thumbnailUrl($product->fresh()->image, $product->fresh()->thumbnail_path));
        (new GenerateProductThumbnail($product->id, $path))->handle(app(ProductThumbnail::class));
        $this->assertSame('https://media.example/media/products/replacement.png', ProductMedia::thumbnailUrl($product->fresh()->image, $product->fresh()->thumbnail_path));
        $this->assertSame('https://media.example/'.$path, ProductMedia::thumbnailUrl($path, null));
    }

    public function test_missing_preview_falls_back_to_local_original(): void
    {
        config(['filesystems.product_legacy_disk' => 'public']);
        Storage::fake('public', ['url' => 'https://shop.example/storage']);
        $missing = 'catalog/thumbnails/v1/'.str_repeat('a', 64).'.webp';

        $this->assertSame('https://shop.example/storage/catalog/photo.jpg', ProductMedia::thumbnailUrl('catalog/photo.jpg', $missing));
    }

    public function test_saving_a_new_or_replaced_image_queues_generation(): void
    {
        Queue::fake();
        $product = Product::query()->create(['name' => 'Battery', 'slug' => 'battery', 'price' => 100, 'image' => 'catalog/first.png']);

        Queue::assertPushed(GenerateProductThumbnail::class, fn ($job): bool => $job->productId === $product->id && $job->sourcePath === 'catalog/first.png');

        Queue::fake();
        $product->update(['image' => 'catalog/second.png']);
        Queue::assertPushed(GenerateProductThumbnail::class, fn ($job): bool => $job->productId === $product->id && $job->sourcePath === 'catalog/second.png');

        Queue::fake();
        $product->update(['price' => 120]);
        Queue::assertNotPushed(GenerateProductThumbnail::class);
    }

    public function test_command_queues_missing_previews_and_force_also_queues_existing(): void
    {
        Queue::fake();
        config(['filesystems.product_legacy_disk' => 'public']);
        $disk = Storage::fake('public');
        $missing = Product::query()->create(['name' => 'Missing', 'slug' => 'missing', 'price' => 100, 'image' => 'catalog/missing.png']);
        $ready = Product::query()->create(['name' => 'Ready', 'slug' => 'ready', 'price' => 100, 'image' => 'catalog/ready.png']);
        $key = ProductMedia::thumbnailSourceKey($ready->image);
        $preview = 'catalog/thumbnails/v1/'.$key.'-'.str_repeat('a', 64).'.webp';
        $disk->put($preview, 'preview');
        $ready->forceFill(['thumbnail_path' => $preview])->save();
        Queue::fake();

        $this->artisan('products:generate-thumbnails')->expectsOutputToContain('В очередь добавлено товаров: 1')->assertSuccessful();
        Queue::assertPushed(GenerateProductThumbnail::class, fn ($job): bool => $job->productId === $missing->id);
        Queue::assertNotPushed(GenerateProductThumbnail::class, fn ($job): bool => $job->productId === $ready->id);

        $this->artisan('products:generate-thumbnails', ['--force' => true])->expectsOutputToContain('В очередь добавлено товаров: 2')->assertSuccessful();
        Queue::assertPushed(GenerateProductThumbnail::class, fn ($job): bool => $job->productId === $ready->id);
    }

    private function imageBytes(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        return $contents;
    }
}
