<?php

namespace Tests\Feature;

use App\Enums\MenuLocation;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_page_shows_contact_options_and_order_steps(): void
    {
        $this->get(route('business'))
            ->assertOk()
            ->assertSee('<h1>Для организаций</h1>', false)
            ->assertSee('Как сделать заказ')
            ->assertSee('href="tel:+'.preg_replace('/\D/', '', config('shop.phone')).'"', false)
            ->assertSee('href="mailto:'.config('shop.email').'"', false);
    }

    public function test_footer_links_to_business_page(): void
    {
        MenuItem::query()->create(['location' => MenuLocation::Footer, 'title' => 'Информация']);

        $this->get(route('home'))
            ->assertSeeInOrder(['Информация', 'Для организаций', 'Наши контакты'])
            ->assertSee('href="'.route('business').'"', false);
    }

    public function test_header_links_to_business_page(): void
    {
        $this->get(route('home'))
            ->assertSeeInOrder(['id="menu2"', 'href="'.route('business').'"', 'Для организаций'], false);
    }
}
