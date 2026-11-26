<?php

namespace App\Console\Commands;

use App\Enums\StoreReviewSource;
use App\Models\StoreReview;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use JsonException;

class ImportStoreReviews extends Command
{
    protected $signature = 'reviews:import {file : Путь к JSON-файлу отзывов} {--dry-run : Проверить файл без сохранения}';

    protected $description = 'Импортировать отзывы из JSON с сохранением на проверку';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('Не удалось прочитать файл отзывов.');

            return self::FAILURE;
        }

        try {
            $reviews = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->error('Некорректный JSON: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! is_array($reviews) || ! array_is_list($reviews) || $reviews === []) {
            $this->error('Файл должен содержать непустой массив отзывов.');

            return self::FAILURE;
        }

        $validator = Validator::make(['reviews' => $reviews], [
            'reviews.*.author' => ['required', 'string', 'max:100'],
            'reviews.*.source' => ['required', Rule::enum(StoreReviewSource::class)],
            'reviews.*.rating' => ['required', 'integer', 'between:1,5'],
            'reviews.*.body' => ['required', 'string'],
            'reviews.*.reviewed_at' => ['required', 'date_format:Y-m-d'],
            'reviews.*.avatar_url' => ['nullable', 'string', 'max:2048', 'url:https', 'regex:~^https://avatars\.mds\.yandex\.net/~i'],
            'reviews.*.photo_urls' => ['nullable', 'array', 'max:20'],
            'reviews.*.photo_urls.*' => ['required', 'string', 'max:2048', 'url:https', 'regex:~^https://avatars\.mds\.yandex\.net/~i'],
        ]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $created = 0;
        $skipped = 0;
        $updated = 0;
        DB::transaction(function () use ($reviews, &$created, &$skipped, &$updated): void {
            $seen = [];
            foreach ($reviews as $review) {
                $attributes = [
                    'source' => $review['source'],
                    'author' => trim($review['author']),
                    'reviewed_at' => $review['reviewed_at'],
                    'body' => trim($review['body']),
                ];
                $key = hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR));
                if (isset($seen[$key])) {
                    $skipped++;

                    continue;
                }
                $seen[$key] = true;
                $existing = StoreReview::query()
                    ->where('source', $attributes['source'])
                    ->where('author', $attributes['author'])
                    ->where('body', $attributes['body'])
                    ->whereDate('reviewed_at', $attributes['reviewed_at'])
                    ->first();
                $avatarUrl = $review['avatar_url'] ?? null;
                if ($existing) {
                    $changes = [];
                    if ($avatarUrl && ! $existing->avatar_url) {
                        $changes['avatar_url'] = $avatarUrl;
                    }
                    if (! empty($review['photo_urls']) && empty($existing->photo_urls)) {
                        $changes['photo_urls'] = $review['photo_urls'];
                    }
                    if ($changes !== []) {
                        if (! $this->option('dry-run')) {
                            $existing->update($changes);
                        }
                        $updated++;

                        continue;
                    }
                    $skipped++;

                    continue;
                }
                if (! $this->option('dry-run')) {
                    StoreReview::query()->create([...$attributes, 'rating' => $review['rating'], 'is_active' => false, 'avatar_url' => $avatarUrl, 'photo_urls' => $review['photo_urls'] ?? null]);
                }
                $created++;
            }
        });

        $this->info(($this->option('dry-run') ? 'Проверка' : 'Импорт').": новых {$created}, обновлено отзывов {$updated}, пропущено {$skipped}.");

        return self::SUCCESS;
    }
}
