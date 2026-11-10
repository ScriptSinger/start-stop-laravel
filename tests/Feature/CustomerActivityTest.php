<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class CustomerActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_activity_is_recorded_at_most_once_per_minute(): void
    {
        $this->travelTo(now()->startOfMinute());
        $customer = Customer::factory()->create();
        $originalUpdatedAt = $customer->updated_at->toDateTimeString();

        $this->actingAs($customer)->get('/')->assertOk();
        $this->assertSame(now()->toDateTimeString(), $customer->fresh()->last_seen_at->toDateTimeString());

        $this->travel(30)->seconds();
        $this->get('/')->assertOk();
        $this->assertSame(now()->subSeconds(30)->toDateTimeString(), $customer->fresh()->last_seen_at->toDateTimeString());

        $this->travel(30)->seconds();
        $this->get('/')->assertOk();
        $this->assertSame(now()->toDateTimeString(), $customer->fresh()->last_seen_at->toDateTimeString());
        $this->assertSame($originalUpdatedAt, $customer->fresh()->updated_at->toDateTimeString());
    }

    public function test_guests_and_admin_pages_do_not_record_customer_visits(): void
    {
        $customer = Customer::factory()->create();
        $admin = MoonshineUser::query()->create(['moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID, 'email' => 'admin@example.com', 'name' => 'Admin', 'password' => bcrypt('secret')]);

        $this->get('/')->assertOk();
        $this->actingAs($customer, 'web')->actingAs($admin, 'moonshine')->get('/admin/resource/customer-resource/customer-index-page')->assertOk();

        $this->assertNull($customer->fresh()->last_seen_at);
    }

    public function test_admin_sees_customer_activity_and_status(): void
    {
        $this->travelTo(now()->startOfMinute());
        $admin = MoonshineUser::query()->create(['moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID, 'email' => 'admin@example.com', 'name' => 'Admin', 'password' => bcrypt('secret')]);
        $online = Customer::factory()->create(['name' => 'Онлайн клиент']);
        $offline = Customer::factory()->create(['name' => 'Неактивный клиент']);
        Customer::query()->whereKey($online)->update(['last_seen_at' => now()->subMinutes(5)]);
        Customer::query()->whereKey($offline)->update(['last_seen_at' => now()->subMinutes(6)]);
        Customer::factory()->create(['name' => 'Клиент без посещений']);

        $this->actingAs($admin, 'moonshine')->get('/admin/component/customer-index-page/customer-resource?_component_name=index-table-customer-resource')
            ->assertOk()->assertSee('Последний визит')->assertSee('Онлайн')->assertSee('Не в сети')->assertSee('Нет данных')
            ->assertSee(now()->subMinutes(5)->format('d.m.Y H:i'));
        $this->get("/admin/resource/customer-resource/customer-detail-page/{$online->id}")
            ->assertOk()->assertSee('Последний визит')->assertSee('Онлайн');
    }
}
