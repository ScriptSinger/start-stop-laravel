<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Catalog\ProductMedia;
use App\Services\Catalog\ProductThumbnail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateProductThumbnail implements ShouldQueue
{
    use Queueable;

    public int $timeout = 25;

    public function __construct(public int $productId, public string $sourcePath)
    {
        $this->onConnection('database');
    }

    /**
     * Execute the job.
     */
    public function handle(ProductThumbnail $generator): void
    {
        if (! Product::query()->whereKey($this->productId)->where('image', $this->sourcePath)->exists()) {
            return;
        }

        $source = ProductMedia::diskFor($this->sourcePath);
        if ($source->size($this->sourcePath) > ProductThumbnail::MAX_BYTES) {
            throw new RuntimeException('Исходное изображение превышает допустимый размер.');
        }

        $contents = $source->get($this->sourcePath);
        if (! is_string($contents)) {
            throw new RuntimeException('Не удалось прочитать исходное изображение.');
        }

        $thumbnail = $generator->make($contents);
        $path = 'catalog/thumbnails/v1/'.ProductMedia::thumbnailSourceKey($this->sourcePath).'-'.hash('sha256', $thumbnail).'.webp';
        $disk = Storage::disk('public');
        if (! $disk->exists($path) && ! $disk->put($path, $thumbnail)) {
            throw new RuntimeException('Не удалось сохранить миниатюру.');
        }

        DB::table('products')->where('id', $this->productId)->where('image', $this->sourcePath)
            ->whereNull('deleted_at')->update(['thumbnail_path' => $path]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Product thumbnail generation failed', [
            'product_id' => $this->productId,
            'exception_type' => $exception ? $exception::class : null,
        ]);
    }
}
