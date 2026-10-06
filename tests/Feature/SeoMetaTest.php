<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class SeoMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_has_shop_title_description_and_open_graph(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<title>Старт-Стоп | Автомобильные аккумуляторы в Уфе</title>', false)
            ->assertSee('<meta name="description" content="Магазин автомобильных аккумуляторов в Уфе.', false)
            ->assertSee('<meta property="og:title" content="Старт-Стоп | Автомобильные аккумуляторы в Уфе" />', false)
            ->assertSee('<meta property="og:type" content="website" />', false)
            ->assertSee('<meta property="og:url" content="'.route('home').'" />', false)
            ->assertSee('<meta property="og:image" content="'.url('/storage/'.config('shop.logo')).'" />', false)
            ->assertSee('<meta name="theme-color" content="#c82a00" />', false);
    }

    public function test_page_uses_own_seo_fields(): void
    {
        Page::query()->create([
            'title' => 'Доставка и оплата',
            'slug' => 'dostavka-i-oplata',
            'heading' => 'Доставка аккумуляторов в Уфе',
            'meta_title' => 'Старт-Стоп | Доставка и оплата',
            'meta_description' => 'Доставка аккумуляторов в Уфе.',
            'status' => true,
        ]);

        $this->get(route('page.show', 'dostavka-i-oplata'))
            ->assertOk()
            ->assertSee('<title>Старт-Стоп | Доставка и оплата</title>', false)
            ->assertSee('<meta name="description" content="Доставка аккумуляторов в Уфе." />', false)
            ->assertSee('<h1>Доставка аккумуляторов в Уфе</h1>', false);
    }

    public function test_page_without_seo_fields_falls_back_to_title(): void
    {
        Page::query()->create(['title' => 'О нас', 'slug' => 'about_us', 'status' => true]);

        $this->get(route('page.show', 'about_us'))
            ->assertOk()
            ->assertSee('<title>О нас — Старт-Стоп</title>', false)
            ->assertSee('<h1>О нас</h1>', false)
            ->assertDontSee('<meta name="description"', false);
    }

    public function test_contact_page_has_heading(): void
    {
        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('<h1>Связаться с нами</h1>', false);
    }

    public function test_missing_page_shows_themed_not_found(): void
    {
        $this->get('/page/net-takoy')
            ->assertNotFound()
            ->assertSee('Запрашиваемая страница не найдена!')
            ->assertSee('id="logo"', false);
    }

    public function test_server_error_shows_page_without_database_partials(): void
    {
        Route::get('/_boom', fn () => throw new RuntimeException('boom'));
        config(['app.debug' => false]);

        $this->get('/_boom')
            ->assertInternalServerError()
            ->assertSee('На сайте что-то пошло не так.')
            ->assertSee(config('shop.phone'));
    }
}
