<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClarityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_script_is_available_on_production_storefront_when_configured(): void
    {
        $this->app['env'] = 'production';
        config(['services.clarity.project_id' => 'test123']);

        $this->get(route('home'))->assertOk()->assertSee('https://www.clarity.ms/tag/', false)->assertSee('test123');
    }

    public function test_script_is_not_loaded_without_id_or_outside_production(): void
    {
        config(['services.clarity.project_id' => 'test123']);
        $this->get(route('home'))->assertDontSee('https://www.clarity.ms/tag/', false);
        $this->app['env'] = 'production';
        config(['services.clarity.project_id' => null]);
        $this->get(route('home'))->assertDontSee('https://www.clarity.ms/tag/', false);
        config(['services.clarity.project_id' => '</script><script>alert(1)</script>']);
        $this->get(route('home'))->assertDontSee('https://www.clarity.ms/tag/', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_script_is_not_loaded_on_authentication_and_private_pages(): void
    {
        $this->app['env'] = 'production';
        config(['services.clarity.project_id' => 'test123']);

        $this->get(route('login'))->assertOk()->assertDontSee('https://www.clarity.ms/tag/', false);
        $this->get(route('register'))->assertOk()->assertDontSee('https://www.clarity.ms/tag/', false);
        $this->get(route('password.request'))->assertOk()->assertDontSee('https://www.clarity.ms/tag/', false);
        $this->get(route('checkout.create'))->assertDontSee('https://www.clarity.ms/tag/', false);
        $this->actingAs(Customer::factory()->create())->get(route('account'))->assertOk()->assertDontSee('https://www.clarity.ms/tag/', false);
    }

    public function test_script_is_loaded_on_cart_page(): void
    {
        $this->app['env'] = 'production';
        config(['services.clarity.project_id' => 'test123']);
        $this->get(route('cart.index'))->assertOk()->assertSee('https://www.clarity.ms/tag/', false);
    }
}
