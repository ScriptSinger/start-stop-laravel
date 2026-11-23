<?php

namespace Tests\Feature;

use App\Models\DeliveryZone;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class DeliveryZonesTest extends TestCase
{
    use RefreshDatabase;

    private const RESOURCE = '/admin/resource/delivery-zone-resource';

    public function test_migration_moves_current_zones_from_config_in_the_same_order(): void
    {
        $zones = DeliveryZone::query()->ordered()->pluck('price', 'name')->all();

        $this->assertSame([
            'Сипайлово' => 0,
            'Зелёная роща' => 0,
            'Центр' => 0,
            'Проспект Октября' => 0,
            'Инорс / Черниковка' => 0,
            'Шакша' => 500,
            'Дема' => 500,
            'Затон' => 500,
        ], $zones);
    }

    public function test_delivery_page_groups_active_zones_by_price(): void
    {
        DeliveryZone::query()->delete();
        DeliveryZone::factory()->create(['name' => 'Дальний', 'price' => 1000, 'sort_order' => 3]);
        DeliveryZone::factory()->create(['name' => 'Ближний', 'price' => 0, 'sort_order' => 1]);
        DeliveryZone::factory()->priced(700)->create(['name' => 'Средний', 'sort_order' => 2]);
        DeliveryZone::factory()->priced(700)->create(['name' => 'Тоже средний', 'sort_order' => 4]);
        DeliveryZone::factory()->inactive()->create(['name' => 'Скрытый район']);
        Page::query()->create(['title' => 'Доставка и оплата', 'slug' => 'dostavka-i-oplata']);

        $this->get(route('page.show', 'dostavka-i-oplata'))
            ->assertOk()
            ->assertSeeInOrder(['Бесплатно', 'Ближний', '700 ₽', 'Средний', 'Тоже средний', '1 000 ₽', 'Дальний'])
            ->assertDontSee('Скрытый район');
    }

    public function test_admin_manages_zones_and_the_site_shows_new_prices(): void
    {
        $this->actingAsAdmin();
        Page::query()->create(['title' => 'Доставка и оплата', 'slug' => 'dostavka-i-oplata']);
        $zone = DeliveryZone::query()->where('name', 'Шакша')->firstOrFail();

        $this->get('/admin')->assertOk()->assertSee('Зоны доставки');
        $this->get(self::RESOURCE.'/delivery-zone-index-page')->assertOk()->assertSee('Шакша');

        $this->put(self::RESOURCE."/crud/{$zone->id}", ['name' => 'Шакша', 'price' => 650, 'sort_order' => 6, 'is_active' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->post(self::RESOURCE.'/crud', ['name' => 'Новый район', 'price' => 800, 'sort_order' => 9, 'is_active' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->get(route('page.show', 'dostavka-i-oplata'))
            ->assertSee('650 ₽')
            ->assertSee('800 ₽')
            ->assertSee('Новый район');
    }

    public function test_admin_cannot_save_negative_price_or_duplicate_name(): void
    {
        $this->actingAsAdmin();

        $this->post(self::RESOURCE.'/crud', ['name' => 'Центр', 'price' => 100, 'sort_order' => 1])
            ->assertSessionHasErrors('name', errorBag: 'delivery-zone-resource');
        $this->post(self::RESOURCE.'/crud', ['name' => 'Новый', 'price' => -1, 'sort_order' => 1])
            ->assertSessionHasErrors('price', errorBag: 'delivery-zone-resource');

        $this->assertSame(8, DeliveryZone::query()->count());
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
