<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportProductImagesTest extends TestCase
{
    use RefreshDatabase;

    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->folder = sys_get_temp_dir().'/product-images-'.uniqid();
        mkdir($this->folder);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);

        parent::tearDown();
    }

    public function test_dry_run_loads_nothing(): void
    {
        $product = $this->product('РТ-1');
        $this->image('РТ-1.jpg');

        $this->artisan('products:import-images', ['folder' => $this->folder])->expectsOutputToContain('Пробный прогон')->assertSuccessful();

        $this->assertNull($product->fresh()->image);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_main_and_gallery_images_are_attached_by_onec_code(): void
    {
        $product = $this->product('РТ-1');
        $this->image('РТ-1.jpg');
        $this->image('РТ-1_2.png', 'png', 1);
        $this->image('1472.webp', 'jpg');

        $this->artisan('products:import-images', ['folder' => $this->folder, '--force' => true])->assertSuccessful();

        $product->refresh();
        $this->assertStringStartsWith('catalog/products/', $product->image);
        $this->assertTrue(Storage::disk('public')->exists($product->image));
        $this->assertSame(1, $product->images()->count());
        $this->assertTrue(Storage::disk('public')->exists($product->images()->first()->path));
    }

    public function test_existing_image_is_never_replaced(): void
    {
        $product = $this->product('РТ-1', 'catalog/own.jpg');
        $this->image('РТ-1.jpg');

        $this->artisan('products:import-images', ['folder' => $this->folder, '--force' => true])->assertSuccessful();

        $this->assertSame('catalog/own.jpg', $product->fresh()->image);
    }

    public function test_unknown_codes_small_and_broken_files_are_skipped(): void
    {
        $product = $this->product('РТ-1');
        $this->image('РТ-999.jpg');
        $this->image('РТ-1.jpg', 'jpg', 0, 100);
        file_put_contents($this->folder.'/РТ-2.jpg', 'not an image');

        $this->artisan('products:import-images', ['folder' => $this->folder, '--force' => true])
            ->expectsOutputToContain('Нет товара')
            ->assertSuccessful();

        $this->assertNull($product->fresh()->image);
    }

    public function test_identical_files_are_stored_once(): void
    {
        $first = $this->product('РТ-1');
        $second = $this->product('РТ-2');
        $this->image('РТ-1.jpg');
        copy($this->folder.'/РТ-1.jpg', $this->folder.'/РТ-2.jpg');

        $this->artisan('products:import-images', ['folder' => $this->folder, '--force' => true])->assertSuccessful();

        $this->assertSame($first->fresh()->image, $second->fresh()->image);
        $this->assertCount(1, Storage::disk('public')->allFiles('catalog/products'));
    }

    public function test_missing_folder_fails(): void
    {
        $this->artisan('products:import-images', ['folder' => '/no/such/folder'])->assertFailed();
    }

    private function product(string $code, ?string $image = null): Product
    {
        return Product::query()->create(['name' => $code, 'slug' => str($code)->slug()->value().'-'.Product::query()->count(), 'price' => 1, 'external_id' => $code, 'image' => $image]);
    }

    private function image(string $name, string $type = 'jpg', int $seed = 0, int $size = 400): void
    {
        $image = imagecreatetruecolor($size, $size);
        imagefill($image, 0, 0, imagecolorallocate($image, 50 + $seed * 40, 100, 150));
        $path = $this->folder.'/'.$name;
        match ($type) {
            'png' => imagepng($image, $path),
            default => imagejpeg($image, $path),
        };
    }
}
