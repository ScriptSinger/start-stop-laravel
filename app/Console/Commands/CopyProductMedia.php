<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Catalog\ProductMedia;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class CopyProductMedia extends Command
{
    protected $signature = 'products:copy-media {--force : Копировать; без флага — только проверка источников}';

    protected $description = 'Копировать главные фото и галереи из public в media, сохраняя пути и проверяя SHA-256';

    public function handle(): int
    {
        $paths = Product::withTrashed()->whereNotNull('image')->pluck('image')
            ->merge(ProductImage::query()->pluck('path'))
            ->filter()->unique()->sort()->values();
        $source = Storage::disk('public');
        $copied = $identical = $errors = $planned = 0;

        foreach ($paths as $path) {
            if (str_starts_with($path, ProductMedia::REMOTE_PREFIX)) {
                continue;
            }

            try {
                ProductMedia::validatePath($path);

                if (! $source->exists($path)) {
                    throw new RuntimeException('Исходный файл отсутствует.', 1);
                }

                if (! $this->option('force')) {
                    $planned++;

                    continue;
                }

                $target = Storage::disk('media');
                $sourceHash = $this->checksum($source, $path);

                if ($target->exists($path)) {
                    if ($target->size($path) !== $source->size($path) || $this->checksum($target, $path) !== $sourceHash) {
                        throw new RuntimeException('Конфликт содержимого: объект не перезаписан.', 2);
                    }

                    $identical++;

                    continue;
                }

                $stream = $source->readStream($path);

                if (! is_resource($stream)) {
                    throw new RuntimeException('Не удалось прочитать исходный файл.');
                }

                try {
                    if (! $target->put($path, $stream, ['ContentType' => $source->mimeType($path)])) {
                        throw new RuntimeException('Не удалось записать объект.');
                    }
                } finally {
                    fclose($stream);
                }

                if ($target->size($path) !== $source->size($path) || $this->checksum($target, $path) !== $sourceHash) {
                    throw new RuntimeException('Контрольная сумма или размер копии не совпадают.', 3);
                }

                $copied++;
                $this->line('Скопировано: '.json_encode($path, JSON_UNESCAPED_UNICODE));
            } catch (Throwable $exception) {
                $errors++;
                $reason = match ($exception::class === RuntimeException::class ? $exception->getCode() : 0) {
                    1 => 'Исходный файл отсутствует.',
                    2 => 'Конфликт содержимого: объект не перезаписан.',
                    3 => 'Контрольная сумма или размер копии не совпадают.',
                    default => 'Ошибка доступа к хранилищу или недопустимый путь.',
                };
                $this->error('Не перенесён путь: '.json_encode($path, JSON_UNESCAPED_UNICODE).' — '.$reason);
                Log::warning('Product media copy failed', ['exception_type' => $exception::class]);
            }
        }

        $this->info("Копий: {$copied}; совпадают: {$identical}; ошибок: {$errors}; к переносу: {$planned}.");

        if (! $this->option('force')) {
            $this->warn('Пробный прогон: запись не выполнялась. Для копирования добавьте --force.');
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function checksum(FilesystemAdapter $disk, string $path): string
    {
        $stream = $disk->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException('Не удалось прочитать изображение для проверки.');
        }

        try {
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);

            return hash_final($hash);
        } finally {
            fclose($stream);
        }
    }
}
