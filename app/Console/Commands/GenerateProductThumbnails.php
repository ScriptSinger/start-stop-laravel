<?php

namespace App\Console\Commands;

use App\Jobs\GenerateProductThumbnail;
use App\Models\Product;
use App\Services\Catalog\ProductMedia;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('products:generate-thumbnails {--force : Пересоздать и существующие миниатюры}')]
#[Description('Добавить создание миниатюр товаров в очередь')]
class GenerateProductThumbnails extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = 0;
        $query = Product::query()->whereNotNull('image')->where('image', '!=', '')->where('image', '!=', 'no_image.png');

        $query->select(['id', 'image', 'thumbnail_path'])->chunkById(200, function ($products) use (&$count): void {
            foreach ($products as $product) {
                if (! $this->option('force') && ProductMedia::hasThumbnail($product->image, $product->thumbnail_path)) {
                    continue;
                }
                GenerateProductThumbnail::dispatch($product->id, $product->image)->afterCommit();
                $count++;
            }
        });

        $this->info("В очередь добавлено товаров: {$count}.");

        return self::SUCCESS;
    }
}
