<?php

namespace Tests\Feature;

use App\Enums\BannerPosition;
use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class AdminBannersTest extends TestCase
{
    use RefreshDatabase;

    private const RESOURCE = '/admin/resource/banner-resource';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]), 'moonshine');
    }

    public function test_banners_are_in_admin_menu_and_listed(): void
    {
        Banner::query()->create(['position' => BannerPosition::HomeSlider, 'title' => 'Трейд-ин', 'image' => 'banners/obmen.png', 'sort_order' => 1, 'is_active' => true]);

        $this->get('/admin')->assertOk()->assertSee('Баннеры');
        $this->get(self::RESOURCE.'/banner-index-page')->assertOk();
        $this->get('/admin/component/banner-index-page/banner-resource?_component_name=index-table-banner-resource')
            ->assertOk()
            ->assertSee('Трейд-ин')
            ->assertSee('Главная: слайдер');
    }

    public function test_new_banner_is_uploaded_and_shown_on_home_page(): void
    {
        $this->post(self::RESOURCE.'/crud', [
            'position' => BannerPosition::HomeSlider->value,
            'image' => UploadedFile::fake()->image('akciya.jpg', 960, 350),
            'title' => 'Акция на ZUBR',
            'url' => '/category/akkumulyatori',
            'sort_order' => 1,
            'is_active' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $banner = Banner::query()->sole();
        Storage::disk('public')->assertExists($banner->image);
        $this->assertStringStartsWith('banners/', $banner->image);

        $this->get('/')->assertSee($banner->imageUrl())->assertSee(url('/category/akkumulyatori'));
    }

    public function test_new_banner_requires_image(): void
    {
        $this->post(self::RESOURCE.'/crud', [
            'position' => BannerPosition::HomeSlider->value,
            'title' => 'Без картинки',
        ])->assertSessionHasErrors('image', errorBag: 'banner-resource');

        $this->assertSame(0, Banner::query()->count());
    }

    public function test_banner_can_be_edited_without_new_image(): void
    {
        $banner = Banner::query()->create(['position' => BannerPosition::HomeSlider, 'image' => 'catalog/revslider_media_folder/obmen.png', 'sort_order' => 1, 'is_active' => true]);

        // Картинка со старого сайта видна в форме по своему пути.
        $this->get(self::RESOURCE."/banner-form-page/{$banner->id}")
            ->assertOk()
            ->assertSee('src="'.Storage::disk('public')->url('catalog/revslider_media_folder/obmen.png').'"', false);

        $this->put(self::RESOURCE."/crud/{$banner->id}", [
            'position' => BannerPosition::HomeStrip->value,
            'hidden_image' => 'catalog/revslider_media_folder/obmen.png',
            'url' => '/page/trade-in',
            'sort_order' => 2,
            'is_active' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $banner->refresh();
        $this->assertSame([BannerPosition::HomeStrip, 'catalog/revslider_media_folder/obmen.png', '/page/trade-in', false], [$banner->position, $banner->image, $banner->url, $banner->is_active]);
    }
}
