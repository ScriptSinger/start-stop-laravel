<?php

namespace Tests\Feature;

use App\Models\StoreReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportStoreReviewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_media_is_copied_without_replacing_source_and_command_is_repeatable(): void
    {
        Storage::fake('media');
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lWQAAAAASUVORK5CYII=');
        Http::fake(['avatars.mds.yandex.net/*' => Http::response($image, 200)]);
        $review = StoreReview::factory()->create(['avatar_url' => 'https://avatars.mds.yandex.net/get-yapic/test', 'photo_urls' => ['https://avatars.mds.yandex.net/get-altay/123/photo/M?referer=lkb']]);

        $this->artisan('reviews:copy-media', ['--dry-run' => true])->assertSuccessful();
        $this->assertNull($review->fresh()->avatar_path);
        $exit = Artisan::call('reviews:copy-media');
        $this->assertSame(0, $exit, Artisan::output());
        $review->refresh();
        Storage::disk('media')->assertExists($review->avatar_path);
        Storage::disk('media')->assertExists($review->photo_paths[0]);
        Storage::disk('media')->assertExists($review->photo_preview_paths[0]);
        Http::assertSent(fn ($request) => $request->url() === 'https://avatars.mds.yandex.net/get-altay/123/photo/orig');
        Http::assertSent(fn ($request) => $request->url() === 'https://avatars.mds.yandex.net/get-altay/123/photo/M');
        $this->assertSame('https://avatars.mds.yandex.net/get-yapic/test', $review->avatar_url);
        $this->artisan('reviews:copy-media')->assertSuccessful();
        Http::assertSentCount(3);
    }

    public function test_review_media_failure_leaves_original_unchanged(): void
    {
        Storage::fake('media');
        Http::fake(['avatars.mds.yandex.net/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/'])]);
        $review = StoreReview::factory()->create(['avatar_url' => 'https://avatars.mds.yandex.net/get-yapic/test']);

        $this->artisan('reviews:copy-media')->assertFailed();
        $this->assertNull($review->fresh()->avatar_path);
        $this->assertSame('https://avatars.mds.yandex.net/get-yapic/test', $review->fresh()->avatar_url);
    }

    public function test_missing_original_does_not_store_preview_as_full_photo(): void
    {
        Storage::fake('media');
        Http::fake(['avatars.mds.yandex.net/*' => Http::response('', 404)]);
        $url = 'https://avatars.mds.yandex.net/get-altay/123/photo/M';
        $review = StoreReview::factory()->create(['photo_urls' => [$url]]);

        $this->artisan('reviews:copy-media')->assertFailed();
        $this->assertNull($review->fresh()->photo_paths);
        $this->assertSame([$url], $review->fresh()->photo_urls);
    }

    public function test_copy_does_not_overwrite_a_concurrent_admin_change(): void
    {
        Storage::fake('media');
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lWQAAAAASUVORK5CYII=');
        $review = StoreReview::factory()->create(['avatar_url' => 'https://avatars.mds.yandex.net/get-yapic/test']);
        Http::fake(function () use ($review, $image) {
            $review->update(['avatar_path' => 'media/reviews/admin.png']);

            return Http::response($image, 200);
        });

        $this->artisan('reviews:copy-media')->assertSuccessful();
        $this->assertSame('media/reviews/admin.png', $review->fresh()->avatar_path);
    }

    public function test_reimport_preserves_admin_edits_using_original_identity(): void
    {
        $file = $this->reviewFile([['author' => 'Автор', 'source' => 'yandex', 'source_external_id' => 'review-123', 'rating' => 5, 'body' => 'Оригинал', 'reviewed_at' => '2025-01-02']]);
        try {
            $this->artisan('reviews:import', ['file' => $file])->assertSuccessful();
            $review = StoreReview::query()->sole();
            $review->update(['author' => 'Новое имя', 'body' => 'Изменённый текст']);
            $this->artisan('reviews:import', ['file' => $file])->assertSuccessful();
            $this->assertDatabaseCount('store_reviews', 1);
            $this->assertSame('Новое имя', $review->fresh()->author);
            $this->assertSame('Изменённый текст', $review->fresh()->body);
        } finally {
            unlink($file);
        }
    }

    public function test_reimport_without_external_id_uses_original_fingerprint(): void
    {
        $file = $this->reviewFile([['author' => 'Автор', 'source' => 'yandex', 'rating' => 5, 'body' => 'Оригинал', 'reviewed_at' => '2025-01-02']]);
        try {
            $this->artisan('reviews:import', ['file' => $file])->assertSuccessful();
            StoreReview::query()->sole()->update(['author' => 'Новое имя']);
            $this->artisan('reviews:import', ['file' => $file])->assertSuccessful();
            $this->assertDatabaseCount('store_reviews', 1);
        } finally {
            unlink($file);
        }
    }

    public function test_distinct_external_ids_with_identical_content_remain_distinct(): void
    {
        $review = ['author' => 'Автор', 'source' => 'yandex', 'rating' => 5, 'body' => 'Оригинал', 'reviewed_at' => '2025-01-02'];
        $file = $this->reviewFile([
            [...$review, 'source_external_id' => 'first'],
            [...$review, 'source_external_id' => 'second'],
        ]);
        try {
            $this->artisan('reviews:import', ['file' => $file])->assertSuccessful();
            $this->assertDatabaseCount('store_reviews', 2);
        } finally {
            unlink($file);
        }
    }

    public function test_import_is_repeatable_and_does_not_publish_or_overwrite_reviews(): void
    {
        $file = $this->reviewFile([['author' => 'Покупатель', 'source' => 'yandex', 'rating' => 4, 'body' => 'Отзыв', 'reviewed_at' => '2025-01-02']]);
        try {
            $this->artisan('reviews:import', ['file' => $file, '--dry-run' => true])->assertSuccessful();
            $this->assertDatabaseCount('store_reviews', 0);
            $this->artisan('reviews:import', ['file' => $file])->assertSuccessful();
            $review = StoreReview::query()->sole();
            $this->assertFalse($review->is_active);
            $review->update(['is_active' => true, 'sort_order' => 9]);
            $this->artisan('reviews:import', ['file' => $file])->assertSuccessful();
            $this->assertDatabaseCount('store_reviews', 1);
            $this->assertTrue($review->fresh()->is_active);
            $this->assertSame(9, $review->fresh()->sort_order);
        } finally {
            unlink($file);
        }
    }

    public function test_invalid_file_does_not_import_valid_rows(): void
    {
        $file = $this->reviewFile([
            ['author' => 'Покупатель', 'source' => 'yandex', 'rating' => 5, 'body' => 'Отзыв', 'reviewed_at' => '2025-01-02'],
            ['author' => 'Покупатель', 'source' => 'yandex', 'rating' => 9, 'body' => 'Отзыв', 'reviewed_at' => '2025-01-02'],
        ]);
        try {
            $this->artisan('reviews:import', ['file' => $file])->assertFailed();
            $this->assertDatabaseCount('store_reviews', 0);
        } finally {
            unlink($file);
        }
    }

    public function test_import_adds_missing_avatar_without_changing_existing_review(): void
    {
        $review = StoreReview::factory()->create(['author' => 'Покупатель', 'source' => 'yandex', 'rating' => 4, 'body' => 'Отзыв', 'reviewed_at' => '2025-01-02', 'is_active' => true, 'sort_order' => 7]);
        $file = $this->reviewFile([['author' => 'Покупатель', 'source' => 'yandex', 'rating' => 5, 'body' => 'Отзыв', 'reviewed_at' => '2025-01-02', 'avatar_url' => 'https://avatars.mds.yandex.net/get-yapic/example']]);
        try {
            $this->artisan('reviews:import', ['file' => $file, '--dry-run' => true])->assertSuccessful();
            $this->assertNull($review->fresh()->avatar_url);
            $this->artisan('reviews:import', ['file' => $file])->assertSuccessful();
            $review->refresh();
            $this->assertSame('https://avatars.mds.yandex.net/get-yapic/example', $review->avatar_url);
            $this->assertSame(4, $review->rating);
            $this->assertSame(7, $review->sort_order);
            $this->assertTrue($review->is_active);
            $this->assertDatabaseCount('store_reviews', 1);
            $this->get(route('home'))->assertSee('https://avatars.mds.yandex.net/get-yapic/example', false);
            $this->artisan('reviews:import', ['file' => $file])->assertSuccessful();
            $this->assertDatabaseCount('store_reviews', 1);
        } finally {
            unlink($file);
        }
    }

    public function test_import_adds_review_photos_without_overwriting_existing_data(): void
    {
        $review = StoreReview::factory()->create(['author' => 'Покупатель', 'source' => 'yandex', 'body' => 'Отзыв', 'reviewed_at' => '2025-01-02', 'is_active' => true]);
        $photo = 'https://avatars.mds.yandex.net/get-altay/example/M';
        $file = $this->reviewFile([['author' => 'Покупатель', 'source' => 'yandex', 'rating' => 5, 'body' => 'Отзыв', 'reviewed_at' => '2025-01-02', 'photo_urls' => [$photo]]]);
        try {
            $this->artisan('reviews:import', ['file' => $file, '--dry-run' => true])->assertSuccessful();
            $this->assertNull($review->fresh()->photo_urls);
            $this->artisan('reviews:import', ['file' => $file])->assertSuccessful();
            $this->assertSame([$photo], $review->fresh()->photo_urls);
            $this->assertTrue($review->fresh()->is_active);
            $this->get(route('home'))->assertSee($photo, false)->assertSee('Смотреть фото');
        } finally {
            unlink($file);
        }
    }

    private function reviewFile(array $reviews): string
    {
        $file = tempnam(sys_get_temp_dir(), 'store-reviews-');
        file_put_contents($file, json_encode($reviews, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $file;
    }
}
