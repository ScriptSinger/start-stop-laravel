<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleTagManagerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_tag_loads_on_production_storefront_and_cart(): void
    {
        $this->app['env'] = 'production';
        config(['services.google_tag_manager.container_id' => 'GTM-ABC12345']);

        foreach ([route('home'), route('cart.index')] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('https://www.googletagmanager.com/gtm.js?id=GTM-ABC12345', false)
                ->assertSee('GTM-ABC12345');
        }
    }

    public function test_tag_is_disabled_when_not_configured_or_outside_production(): void
    {
        config(['services.google_tag_manager.container_id' => 'GTM-ABC12345']);
        $this->get(route('home'))->assertDontSee('googletagmanager.com/gtm.js', false);
        $this->app['env'] = 'production';
        foreach ([null, '', '</script><script>alert(1)</script>', 'GTM-INVALID?bad'] as $id) {
            config(['services.google_tag_manager.container_id' => $id]);
            $this->get(route('home'))->assertDontSee('googletagmanager.com/gtm.js', false)->assertDontSee('<script>alert(1)</script>', false);
        }
    }

    public function test_tag_is_not_loaded_on_authentication_and_account_pages(): void
    {
        $this->app['env'] = 'production';
        config(['services.google_tag_manager.container_id' => 'GTM-ABC12345']);
        $this->get(route('login'))->assertOk()->assertDontSee('googletagmanager.com/gtm.js', false);
        $this->get(route('register'))->assertOk()->assertDontSee('googletagmanager.com/gtm.js', false);
        $this->get(route('password.request'))->assertOk()->assertDontSee('googletagmanager.com/gtm.js', false);
        $this->actingAs(Customer::factory()->create())->get(route('account'))->assertOk()->assertDontSee('googletagmanager.com/gtm.js', false);
    }
}
