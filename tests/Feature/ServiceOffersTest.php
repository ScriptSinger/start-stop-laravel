<?php

namespace Tests\Feature;

use App\Enums\ServicePriceType;
use App\Models\Page;
use App\Models\ServiceOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class ServiceOffersTest extends TestCase
{
    use RefreshDatabase;

    private const RESOURCE = '/admin/resource/service-offer-resource';

    private const ERRORS = 'service-offer-resource';

    public function test_migration_moves_current_offers_from_config_with_the_same_wording(): void
    {
        $labels = ServiceOffer::query()->ordered()->get()->mapWithKeys(fn (ServiceOffer $offer): array => [$offer->slug => $offer->priceLabel()])->all();

        $this->assertSame([
            'battery-check' => 'Бесплатно',
            'maintenance' => 'Бесплатно для наших клиентов в течение гарантийного периода',
            'car-check' => 'Бесплатно при покупке аккумулятора в нашем магазине',
            'installation' => 'Стоимость согласовывается индивидуально',
            'express-check' => 'Условия уточняйте по телефону',
        ], $labels);
    }

    public function test_services_page_shows_active_offers_in_order_with_price_labels(): void
    {
        ServiceOffer::query()->delete();
        ServiceOffer::factory()->create(['title' => 'Вторая', 'sort_order' => 2]);
        ServiceOffer::factory()->priced(ServicePriceType::From, 800)->create(['title' => 'Первая', 'sort_order' => 1, 'terms' => 'за час']);
        ServiceOffer::factory()->inactive()->create(['title' => 'Скрытая услуга']);
        Page::query()->create(['title' => 'Услуги', 'slug' => 'uslugi']);

        $this->get(route('page.show', 'uslugi'))
            ->assertOk()
            ->assertSeeInOrder(['Первая', 'от 800 ₽ за час', 'Вторая', 'Бесплатно'])
            ->assertDontSee('Скрытая услуга');
    }

    public function test_meta_description_lists_active_service_titles(): void
    {
        ServiceOffer::query()->delete();
        ServiceOffer::factory()->create(['title' => 'Зарядка АКБ']);
        ServiceOffer::factory()->inactive()->create(['title' => 'Скрытая услуга']);
        Page::query()->create(['title' => 'Услуги', 'slug' => 'uslugi']);

        $this->get(route('page.show', 'uslugi'))
            ->assertSee('Услуги магазина «Старт-Стоп» в Уфе: зарядка акб.', false)
            ->assertDontSee('скрытая услуга');
    }

    public function test_admin_changes_price_type_and_amount_and_the_site_shows_it(): void
    {
        $this->actingAsAdmin();
        Page::query()->create(['title' => 'Услуги', 'slug' => 'uslugi']);
        $offer = ServiceOffer::query()->where('slug', 'installation')->firstOrFail();

        $this->get('/admin')->assertOk()->assertSee('Услуги');
        $this->get(self::RESOURCE.'/service-offer-index-page')->assertOk()->assertSee('Установка аккумулятора');

        $this->put(self::RESOURCE."/crud/{$offer->id}", $this->payload($offer, ['price_type' => 'fixed', 'price' => 1200]))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->get(route('page.show', 'uslugi'))->assertSee('1 200 ₽');
    }

    public function test_list_lets_edit_the_amount_only_for_types_that_use_it(): void
    {
        $this->actingAsAdmin();
        ServiceOffer::query()->delete();
        ServiceOffer::factory()->create(['title' => 'Бесплатная услуга']);
        ServiceOffer::factory()->priced(ServicePriceType::Fixed, 731)->create(['title' => 'Платная услуга']);

        $html = $this->get(self::RESOURCE.'/service-offer-index-page')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-field-selector="price"'));
        $this->assertStringContainsString('value="731"', $html);
    }

    public function test_amount_is_required_only_for_fixed_and_from_types(): void
    {
        $this->actingAsAdmin();
        $offer = ServiceOffer::query()->where('slug', 'installation')->firstOrFail();

        $this->put(self::RESOURCE."/crud/{$offer->id}", $this->payload($offer, ['price_type' => 'from', 'price' => null]))
            ->assertSessionHasErrors('price', errorBag: self::ERRORS);
        $this->put(self::RESOURCE."/crud/{$offer->id}", $this->payload($offer, ['price_type' => 'by_phone', 'price' => null]))
            ->assertSessionHasNoErrors();
    }

    public function test_slug_is_unique_well_formed_and_cannot_change_after_creation(): void
    {
        $this->actingAsAdmin();
        $offer = ServiceOffer::query()->where('slug', 'installation')->firstOrFail();

        $this->put(self::RESOURCE."/crud/{$offer->id}", $this->payload($offer, ['slug' => 'other-slug']))
            ->assertSessionHasErrors('slug', errorBag: self::ERRORS);
        $this->post(self::RESOURCE.'/crud', $this->payload($offer, ['slug' => 'maintenance']))
            ->assertSessionHasErrors('slug', errorBag: self::ERRORS);
        $this->post(self::RESOURCE.'/crud', $this->payload($offer, ['slug' => 'Bad Slug!']))
            ->assertSessionHasErrors('slug', errorBag: self::ERRORS);
        $this->post(self::RESOURCE.'/crud', $this->payload($offer, ['slug' => 'new-service', 'title' => 'Новая']))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('installation', $offer->fresh()->slug);
        $this->assertTrue(ServiceOffer::query()->where('slug', 'new-service')->exists());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(ServiceOffer $offer, array $overrides = []): array
    {
        return [
            'slug' => $offer->slug,
            'title' => $offer->title,
            'icon' => $offer->icon,
            'description' => $offer->description,
            'price_type' => $offer->price_type->value,
            'price' => $offer->price,
            'terms' => $offer->terms,
            'sort_order' => $offer->sort_order,
            'is_active' => 1,
            ...$overrides,
        ];
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
