<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\StoreReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class StoreReviewsTest extends TestCase
{
    use RefreshDatabase;

    private const RESOURCE = '/admin/resource/store-review-resource';

    public function test_public_pages_show_only_published_reviews_in_order_with_actual_rating(): void
    {
        Page::query()->create(['title' => 'О нас', 'slug' => 'about_us']);
        StoreReview::factory()->create(['author' => 'Вторая', 'sort_order' => 2, 'source' => 'avito', 'rating' => 3]);
        StoreReview::factory()->create(['author' => 'Первая', 'sort_order' => 1, 'source' => 'yandex', 'rating' => 4, 'body' => '<script>alert(1)</script>']);
        StoreReview::factory()->create(['author' => 'Скрытая', 'is_active' => false]);

        foreach ([route('home'), route('page.show', 'about_us')] as $url) {
            $this->get($url)->assertSeeInOrder(['Первая', 'Вторая'])->assertSee('Оценка: 4 из 5')
                ->assertSee('★★★☆☆')->assertDontSee('Скрытая')->assertDontSee('<script>alert(1)</script>', false);
        }
    }

    public function test_empty_reviews_are_not_replaced_with_testimonials(): void
    {
        $this->get(route('home'))->assertDontSee('Отзывы о магазине')->assertDontSee('Имя автора 1');
    }

    public function test_storefront_prefers_copied_media_over_source_urls(): void
    {
        Storage::fake('media');
        StoreReview::factory()->create([
            'avatar_path' => 'media/reviews/avatar.png',
            'photo_paths' => ['media/reviews/photo.png'],
            'avatar_url' => 'https://avatars.mds.yandex.net/get-yapic/source',
            'photo_urls' => ['https://avatars.mds.yandex.net/get-altay/source'],
        ]);

        $this->get(route('home'))
            ->assertSee(Storage::disk('media')->url('media/reviews/avatar.png'), false)
            ->assertSee(Storage::disk('media')->url('media/reviews/photo.png'), false)
            ->assertDontSee('https://avatars.mds.yandex.net/get-yapic/source', false)
            ->assertDontSee('https://avatars.mds.yandex.net/get-altay/source', false);
    }

    public function test_source_photo_uses_preview_in_card_and_original_in_gallery(): void
    {
        StoreReview::factory()->create(['photo_urls' => ['https://avatars.mds.yandex.net/get-altay/123/photo/M?referer=lkb']]);

        $this->get(route('home'))
            ->assertSee('https://avatars.mds.yandex.net/get-altay/123/photo/M?referer=lkb', false)
            ->assertSee('https://avatars.mds.yandex.net/get-altay/123/photo/orig', false);
    }

    public function test_admin_can_create_edit_and_unpublish_a_review(): void
    {
        $this->actingAsAdmin();
        $this->get(self::RESOURCE.'/store-review-index-page')->assertOk();
        $this->get(self::RESOURCE.'/store-review-form-page')->assertOk()->assertSee('Текст отзыва')->assertSee('Яндекс Карты');
        $this->post(self::RESOURCE.'/crud', $this->payload())->assertRedirect()->assertSessionHasNoErrors();
        $review = StoreReview::query()->sole();
        $this->get(route('home'))->assertSee('Отзыв покупателя');
        $this->put(self::RESOURCE.'/crud/'.$review->id, $this->payload(['body' => 'Изменённый текст', 'is_active' => 0]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('home'))->assertDontSee('Изменённый текст');
        $this->assertSame('Изменённый текст', $review->fresh()->body);
    }

    public function test_admin_can_upload_review_images(): void
    {
        Storage::fake('media');
        $this->actingAsAdmin();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lWQAAAAASUVORK5CYII=');
        $this->post(self::RESOURCE.'/crud', $this->payload([
            'avatar_path' => UploadedFile::fake()->createWithContent('avatar.png', $png),
            'photo_paths' => [UploadedFile::fake()->createWithContent('photo.png', $png)],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $review = StoreReview::query()->sole();
        Storage::disk('media')->assertExists($review->avatar_path);
        Storage::disk('media')->assertExists($review->photo_paths[0]);
    }

    public function test_removing_media_in_admin_does_not_restore_the_old_source(): void
    {
        Storage::fake('media');
        $this->actingAsAdmin();
        $review = StoreReview::factory()->create([
            'avatar_path' => 'media/reviews/avatar.png',
            'photo_paths' => ['media/reviews/photo.png'],
            'avatar_url' => 'https://avatars.mds.yandex.net/get-yapic/test',
            'photo_urls' => ['https://avatars.mds.yandex.net/get-altay/test'],
        ]);

        $this->put(self::RESOURCE.'/crud/'.$review->id, $this->payload())->assertRedirect()->assertSessionHasNoErrors();
        $review->refresh();
        $this->assertNull($review->avatar_path);
        $this->assertEmpty($review->photo_paths);
        $this->assertNull($review->avatar_url);
        $this->assertNull($review->photo_urls);
    }

    public function test_admin_cannot_save_invalid_review(): void
    {
        $this->actingAsAdmin();
        $this->post(self::RESOURCE.'/crud', $this->payload(['author' => '', 'source' => 'unknown', 'rating' => 6, 'body' => '']))
            ->assertSessionHasErrors(['author', 'source', 'rating', 'body'], errorBag: 'store-review-resource');
        $this->assertDatabaseCount('store_reviews', 0);
    }

    public function test_new_review_is_hidden_until_staff_publishes_it(): void
    {
        $this->actingAsAdmin();
        $payload = $this->payload();
        unset($payload['is_active']);

        $this->post(self::RESOURCE.'/crud', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse(StoreReview::query()->sole()->is_active);
        $this->get(route('home'))->assertDontSee('Отзыв покупателя');
    }

    public function test_admin_can_delete_a_review(): void
    {
        $this->actingAsAdmin();
        $review = StoreReview::factory()->create();

        $this->delete(self::RESOURCE.'/crud/'.$review->id)->assertRedirect();
        $this->assertModelMissing($review);
    }

    public function test_guest_cannot_access_admin_resource(): void
    {
        $this->get(self::RESOURCE.'/store-review-index-page')->assertRedirect();
        $this->post(self::RESOURCE.'/crud', $this->payload())->assertRedirect();
        $this->assertDatabaseCount('store_reviews', 0);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['author' => 'Анна', 'source' => 'yandex', 'rating' => 5, 'body' => 'Отзыв покупателя', 'reviewed_at' => '2025-01-01', 'sort_order' => 1, 'is_active' => 1], $overrides);
    }

    private function actingAsAdmin(): void
    {
        $this->actingAs(MoonshineUser::query()->create(['moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID, 'email' => 'review-admin@example.test', 'name' => 'Admin', 'password' => bcrypt('secret')]), 'moonshine');
    }
}
