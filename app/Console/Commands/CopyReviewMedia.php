<?php

namespace App\Console\Commands;

use App\Models\StoreReview;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class CopyReviewMedia extends Command
{
    protected $signature = 'reviews:copy-media {--dry-run : Показать число отзывов без копирования} {--review= : Идентификатор отзыва}';

    protected $description = 'Скопировать изображения отзывов Яндекса в медиа-хранилище';

    public function handle(): int
    {
        $failed = 0;
        $updated = 0;
        StoreReview::query()->when($this->option('review'), fn ($query, $id) => $query->whereKey($id))->orderBy('id')->chunkById(100, function ($reviews) use (&$failed, &$updated): void {
            foreach ($reviews as $review) {
                if ((! $review->avatar_url || $review->avatar_path) && (empty($review->photo_urls) || ! empty($review->photo_paths))) {
                    continue;
                }

                if ($this->option('dry-run')) {
                    $updated++;

                    continue;
                }

                try {
                    $changes = [];
                    if ($review->avatar_url && ! $review->avatar_path) {
                        $changes['avatar_path'] = $this->copyImage($review->avatar_url);
                    }
                    if (! empty($review->photo_urls) && empty($review->photo_paths)) {
                        $changes['photo_paths'] = [];
                        $changes['photo_preview_paths'] = [];
                        foreach ($review->photo_urls as $url) {
                            [$original, $preview] = $this->photoVariants($url);
                            $changes['photo_paths'][] = $this->copyImage($original);
                            $changes['photo_preview_paths'][] = $this->copyImage($preview);
                        }
                    }
                    $saved = DB::transaction(function () use ($review, $changes): bool {
                        $current = StoreReview::query()->whereKey($review->id)->lockForUpdate()->first();
                        if (! $current) {
                            return false;
                        }

                        $safeChanges = [];
                        if (isset($changes['avatar_path']) && ! $current->avatar_path && $current->avatar_url === $review->avatar_url) {
                            $safeChanges['avatar_path'] = $changes['avatar_path'];
                        }
                        if (isset($changes['photo_paths']) && empty($current->photo_paths) && $current->photo_urls === $review->photo_urls) {
                            $safeChanges['photo_paths'] = $changes['photo_paths'];
                            $safeChanges['photo_preview_paths'] = $changes['photo_preview_paths'];
                        }
                        if ($safeChanges === []) {
                            return false;
                        }

                        $current->update($safeChanges);

                        return true;
                    });
                    $updated += (int) $saved;
                } catch (Throwable $exception) {
                    $failed++;
                    $this->warn("Не удалось скопировать изображения отзыва #{$review->id} (".$exception::class.').');
                }
            }
        });

        $this->info("Отзывов: {$updated}, ошибок: {$failed}.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function photoVariants(string $url): array
    {
        if (! preg_match('~^(https://avatars\.mds\.yandex\.net/get-altay/[A-Za-z0-9_-]+/[A-Za-z0-9_-]+/)[A-Za-z0-9_-]+(?:\?[^#]*)?$~D', $url, $matches)) {
            throw new RuntimeException('Не удалось определить оригинал фотографии.');
        }

        return [$matches[1].'orig', $matches[1].'M'];
    }

    private function copyImage(string $url): string
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'avatars.mds.yandex.net' || isset($parts['user']) || isset($parts['port'])) {
            throw new RuntimeException('Недопустимый адрес изображения.');
        }

        $response = Http::connectTimeout(5)->timeout(20)->withoutRedirecting()->withOptions(['stream' => true])->get($url);
        if (! $response->successful()) {
            throw new RuntimeException('Источник изображения недоступен.');
        }
        $stream = $response->toPsrResponse()->getBody();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        $contents = '';
        while (! $stream->eof()) {
            $contents .= $stream->read(65536);
            if (strlen($contents) > 30 * 1024 * 1024) {
                throw new RuntimeException('Изображение превышает 30 МБ.');
            }
        }

        $image = @getimagesizefromstring($contents);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (! isset($extensions[$image['mime'] ?? ''])) {
            throw new RuntimeException('Неподдерживаемый формат изображения.');
        }

        $path = 'media/reviews/imported/'.hash('sha256', $url).'.'.$extensions[$image['mime']];
        if (! Storage::disk('media')->put($path, $contents, ['ContentType' => $image['mime']])) {
            throw new RuntimeException('Ошибка записи в медиа-хранилище.');
        }

        return $path;
    }
}
