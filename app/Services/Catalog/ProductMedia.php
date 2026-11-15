<?php

namespace App\Services\Catalog;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ProductMedia
{
    public const REMOTE_PREFIX = 'media/products/';

    public static function uploadDisk(): string
    {
        return self::configuredDisk('filesystems.product_upload_disk');
    }

    public static function uploadDirectory(): string
    {
        return self::uploadDisk() === 'media' ? rtrim(self::REMOTE_PREFIX, '/') : 'catalog/products';
    }

    public static function diskFor(string $path): FilesystemAdapter
    {
        self::validatePath($path);

        return Storage::disk(str_starts_with($path, self::REMOTE_PREFIX)
            ? 'media'
            : self::configuredDisk('filesystems.product_legacy_disk'));
    }

    public static function url(?string $path): string
    {
        if (blank($path) || $path === 'no_image.png') {
            return Storage::disk('public')->url('no_image.png');
        }

        return self::diskFor($path)->url($path);
    }

    public static function thumbnailUrl(?string $original, ?string $thumbnail): string
    {
        if (self::hasThumbnail($original, $thumbnail)) {
            return Storage::disk('public')->url($thumbnail);
        }

        return self::url($original);
    }

    public static function hasThumbnail(?string $original, ?string $thumbnail): bool
    {
        return filled($original) && filled($thumbnail)
            && preg_match('~^catalog/thumbnails/v1/([a-f0-9]{64})-[a-f0-9]{64}\.webp$~D', $thumbnail, $matches)
            && hash_equals(self::thumbnailSourceKey($original), $matches[1])
            && Storage::disk('public')->exists($thumbnail);
    }

    public static function thumbnailSourceKey(string $path): string
    {
        self::validatePath($path);
        $disk = str_starts_with($path, self::REMOTE_PREFIX) ? 'media' : self::configuredDisk('filesystems.product_legacy_disk');

        return hash('sha256', $disk.':'.$path);
    }

    public static function validatePath(string $path): void
    {
        if ($path === '' || str_starts_with($path, '/') || preg_match('~(^|/)\.{1,2}(/|$)|[\\\\\x00-\x1F:?#]~', $path)) {
            throw new InvalidArgumentException('Недопустимый относительный путь изображения.');
        }
    }

    private static function configuredDisk(string $key): string
    {
        $disk = config($key, 'public');

        if (! in_array($disk, ['public', 'media'], true)) {
            throw new InvalidArgumentException('Диск изображений должен быть public или media.');
        }

        return $disk;
    }
}
