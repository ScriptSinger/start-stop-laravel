<?php

namespace Tests\Feature;

use App\Models\StoreReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportStoreReviewsTest extends TestCase
{
    use RefreshDatabase;

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
