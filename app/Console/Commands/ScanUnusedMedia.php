<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\StoreReview;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ScanUnusedMedia extends Command
{
    protected $signature = 'media:scan-unused {--list : Показать найденные пути и дату первого обнаружения}';

    protected $description = 'Найти неиспользуемые изображения товаров и отзывов без удаления';

    public function handle(): int
    {
        try {
            $disk = Storage::disk('media');
            $referenced = [];
            StoreReview::query()->select(['id', 'avatar_path', 'photo_paths', 'photo_preview_paths'])->chunkById(200, function ($reviews) use (&$referenced): void {
                foreach ($reviews as $review) {
                    foreach (array_merge([$review->avatar_path], $review->photo_paths ?? [], $review->photo_preview_paths ?? []) as $path) {
                        if (is_string($path)) {
                            $referenced[$path] = true;
                        }
                    }
                }
            });

            Product::withTrashed()->select(['id', 'image', 'thumbnail_path'])->chunkById(200, function ($products) use (&$referenced): void {
                foreach ($products as $product) {
                    foreach ([$product->image, $product->thumbnail_path] as $path) {
                        if (is_string($path) && $path !== '') {
                            $referenced[$path] = true;
                        }
                    }
                }
            });
            ProductImage::query()->select(['id', 'path'])->chunkById(200, function ($images) use (&$referenced): void {
                foreach ($images as $image) {
                    if (is_string($image->path) && $image->path !== '') {
                        $referenced[$image->path] = true;
                    }
                }
            });

            $objects = [];
            foreach (['media/reviews/', 'media/products/', 'catalog/thumbnails/v2/'] as $prefix) {
                foreach ($disk->allFiles(rtrim($prefix, '/')) as $path) {
                    if (str_starts_with($path, $prefix) && ! isset($referenced[$path])) {
                        $objects[$path] = $disk->lastModified($path);
                    }
                }
            }

            DB::transaction(function () use ($objects, $referenced): void {
                $candidates = DB::table('media_cleanup_candidates')->get()->keyBy('path_hash');
                foreach ($candidates as $hash => $candidate) {
                    if (! isset($objects[$candidate->path]) || isset($referenced[$candidate->path]) || $objects[$candidate->path] !== (int) $candidate->last_modified) {
                        DB::table('media_cleanup_candidates')->where('path_hash', $hash)->delete();
                        unset($candidates[$hash]);
                    }
                }
                foreach ($objects as $path => $lastModified) {
                    $hash = hash('sha256', $path);
                    if (! isset($referenced[$path]) && ! isset($candidates[$hash])) {
                        DB::table('media_cleanup_candidates')->insert(['path' => $path, 'path_hash' => $hash, 'last_modified' => $lastModified, 'unreferenced_since' => now()]);
                    }
                }
            });

            $count = DB::table('media_cleanup_candidates')->count();
            $eligible = DB::table('media_cleanup_candidates')->where('unreferenced_since', '<=', now()->subDays(30))->count();
            $this->info("Кандидатов: {$count}; впервые найдены не менее 30 дней назад: {$eligible}. Файлы не удалены.");
            if ($this->option('list')) {
                $rows = DB::table('media_cleanup_candidates')->orderBy('unreferenced_since')->get(['path', 'unreferenced_since']);
                $this->table(['Путь', 'Впервые обнаружен'], $rows->map(fn ($row) => [$row->path, $row->unreferenced_since])->all());
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Сканирование не выполнено ('.$exception::class.').');

            return self::FAILURE;
        }
    }
}
