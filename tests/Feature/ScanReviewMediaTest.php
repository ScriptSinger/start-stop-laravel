<?php

namespace Tests\Feature;

use App\Models\StoreReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScanReviewMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_tracks_unreferenced_media_without_deleting_it_and_forgets_referenced_paths(): void
    {
        Storage::fake('media');
        $disk = Storage::disk('media');
        $disk->put('media/reviews/used.png', 'used');
        $disk->put('media/reviews/unused.png', 'unused');
        $disk->put('media/products/other.png', 'other');
        $review = StoreReview::factory()->create(['photo_paths' => ['media/reviews/used.png']]);

        $this->artisan('media:scan-unused')->assertSuccessful();
        $this->assertDatabaseHas('media_cleanup_candidates', ['path' => 'media/reviews/unused.png']);
        $this->assertDatabaseMissing('media_cleanup_candidates', ['path' => 'media/reviews/used.png']);
        $disk->assertExists('media/reviews/unused.png');

        $firstSeen = DB::table('media_cleanup_candidates')->where('path', 'media/reviews/unused.png')->value('unreferenced_since');
        $this->travel(5)->days();
        $this->artisan('media:scan-unused')->assertSuccessful();
        $this->assertSame($firstSeen, DB::table('media_cleanup_candidates')->where('path', 'media/reviews/unused.png')->value('unreferenced_since'));

        $review->update(['avatar_path' => 'media/reviews/unused.png']);
        $this->artisan('media:scan-unused')->assertSuccessful();
        $this->assertDatabaseMissing('media_cleanup_candidates', ['path' => 'media/reviews/unused.png']);
        $disk->assertExists('media/reviews/unused.png');
    }

    public function test_scan_resets_observation_if_object_changes(): void
    {
        Storage::fake('media');
        Storage::disk('media')->put('media/reviews/orphan.png', 'file');
        $this->artisan('media:scan-unused')->assertSuccessful();
        DB::table('media_cleanup_candidates')->update(['last_modified' => 1]);
        $this->travel(40)->days();
        $this->artisan('media:scan-unused')->assertSuccessful();
        $this->assertDatabaseHas('media_cleanup_candidates', ['path' => 'media/reviews/orphan.png', 'unreferenced_since' => now()->toDateTimeString()]);
    }

    public function test_product_references_including_deleted_products_are_protected(): void
    {
        Storage::fake('media');
        foreach (['media/products/main.png', 'media/products/gallery.png', 'catalog/thumbnails/v2/thumb.webp', 'media/products/orphan.png', 'catalog/legacy.png'] as $path) {
            Storage::disk('media')->put($path, 'file');
        }
        $id = DB::table('products')->insertGetId(['name' => 'Товар', 'slug' => 'product', 'image' => 'media/products/main.png', 'thumbnail_path' => 'catalog/thumbnails/v2/thumb.webp', 'deleted_at' => now()]);
        DB::table('product_images')->insert(['product_id' => $id, 'path' => 'media/products/gallery.png', 'sort_order' => 0]);

        $this->artisan('media:scan-unused')->assertSuccessful();
        foreach (['media/products/main.png', 'media/products/gallery.png', 'catalog/thumbnails/v2/thumb.webp', 'catalog/legacy.png'] as $path) {
            $this->assertDatabaseMissing('media_cleanup_candidates', ['path' => $path]);
        }
        $this->assertDatabaseHas('media_cleanup_candidates', ['path' => 'media/products/orphan.png']);
        Storage::disk('media')->assertExists('media/products/orphan.png');
    }
}
