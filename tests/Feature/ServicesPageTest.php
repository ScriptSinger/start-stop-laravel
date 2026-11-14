<?php

namespace Tests\Feature;

use App\Enums\MenuLocation;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_page_shows_offers_and_direct_navigation_without_submenu(): void
    {
        Page::query()->create(['title' => 'Услуги', 'slug' => 'uslugi', 'description' => 'Старый текст']);
        $menu = MenuItem::query()->create(['location' => MenuLocation::Main, 'title' => 'Услуги', 'url' => '/page/uslugi']);
        MenuItem::query()->create(['location' => MenuLocation::Main, 'parent_id' => $menu->id, 'title' => 'Старый подпункт услуг', 'url' => '/page/service']);

        $this->get(route('page.show', 'uslugi'))
            ->assertOk()
            ->assertSee('Услуги для аккумуляторов и автомобиля')
            ->assertSee('Бесплатно при покупке аккумулятора в нашем магазине')
            ->assertSee('Стоимость согласовывается индивидуально')
            ->assertSee('href="'.route('page.show', 'uslugi').'"', false)
            ->assertDontSee('Старый подпункт услуг')
            ->assertDontSee('href="'.route('page.show', 'uslugi').'#express-check"', false)
            ->assertSee('href="'.route('callback.create').'"', false)
            ->assertDontSee('Старый текст')
            ->assertDontSee('id="column-left"', false);
    }

    public function test_other_information_pages_keep_their_content(): void
    {
        Page::query()->create(['title' => 'Доставка', 'slug' => 'delivery', 'description' => 'Условия доставки']);

        $this->get(route('page.show', 'delivery'))
            ->assertOk()
            ->assertSee('Условия доставки');
    }
}
