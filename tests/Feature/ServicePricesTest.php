<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\ServicePrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class ServicePricesTest extends TestCase
{
    use RefreshDatabase;

    private const RESOURCE = '/admin/resource/service-price-resource';

    private const ERRORS = 'service-price-resource';

    private const LEGACY_TEXT = '<p>Что входит в стоимость.</p><table><tr><th>Ёмкость аккумулятора</th><th>Цена</th></tr><tr><td>3-30 Ah</td><td>999 руб.</td></tr></table><p>Конец</p>';

    public function test_migration_moves_the_current_table_from_the_page(): void
    {
        $rows = ServicePrice::query()->ordered()->get()->map(fn (ServicePrice $price): string => "{$price->capacityLabel()} {$price->price}")->all();

        $this->assertSame([
            '3-30 Ah 200', '35-65 Ah 500', '66-78 Ah 600', '80-85 Ah 700',
            '90-110 Ah 800', '132-140 Ah 900', '180-230 Ah 1000',
        ], $rows);
    }

    public function test_service_page_replaces_the_hand_made_table_with_prices_from_the_database(): void
    {
        Page::query()->create(['title' => 'Обслуживание', 'slug' => 'service', 'description' => self::LEGACY_TEXT]);

        $this->get(route('page.show', 'service'))
            ->assertOk()
            ->assertSeeInOrder(['Что входит в стоимость.', '3-30 Ah', '200', '35-65 Ah', '500', '180-230 Ah', 'Конец'])
            ->assertDontSee('999 руб.');
    }

    public function test_marker_renders_the_table_on_any_page_and_other_pages_without_marker_stay_untouched(): void
    {
        ServicePrice::query()->delete();
        ServicePrice::factory()->create(['capacity_from' => 40, 'capacity_to' => 60, 'price' => 550, 'sort_order' => 2]);
        ServicePrice::factory()->create(['capacity_from' => 5, 'capacity_to' => 30, 'price' => 250, 'sort_order' => 1]);
        Page::query()->create(['title' => 'Про зарядку', 'slug' => 'charging', 'description' => '<p>[[block:service-prices]]</p><p>Хвост</p>']);
        Page::query()->create(['title' => 'Другая', 'slug' => 'other', 'description' => '<p>Без метки</p>']);

        $this->get(route('page.show', 'charging'))
            ->assertSeeInOrder(['5-30 Ah', '250', '40-60 Ah', '550', 'Хвост'])
            ->assertDontSee('[[block:service-prices]]');
        $this->get(route('page.show', 'other'))->assertDontSee('<th scope="col">Ёмкость аккумулятора</th>', false);
    }

    public function test_admin_changes_price_and_the_site_shows_it(): void
    {
        $this->actingAsAdmin();
        Page::query()->create(['title' => 'Обслуживание', 'slug' => 'service', 'description' => '<p>[[block:service-prices]]</p>']);
        $price = ServicePrice::query()->where('capacity_from', 35)->firstOrFail();

        $this->get('/admin')->assertOk()->assertSee('Цены на обслуживание');
        $this->get(self::RESOURCE.'/service-price-index-page')->assertOk()->assertSee('35-65 Ah');

        $this->put(self::RESOURCE."/crud/{$price->id}", ['capacity_from' => 35, 'capacity_to' => 65, 'price' => 550, 'sort_order' => 2])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->get(route('page.show', 'service'))->assertSee('550');
        $this->assertSame(550, $price->fresh()->price);
    }

    public function test_admin_cannot_save_inverted_range_or_negative_price(): void
    {
        $this->actingAsAdmin();

        $this->post(self::RESOURCE.'/crud', ['capacity_from' => 60, 'capacity_to' => 50, 'price' => 100])
            ->assertSessionHasErrors('capacity_to', errorBag: self::ERRORS);
        $this->post(self::RESOURCE.'/crud', ['capacity_from' => 1, 'capacity_to' => 5, 'price' => -1])
            ->assertSessionHasErrors('price', errorBag: self::ERRORS);

        $this->assertSame(7, ServicePrice::query()->count());
    }

    private function actingAsAdmin(): void
    {
        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]), 'moonshine');
    }
}
