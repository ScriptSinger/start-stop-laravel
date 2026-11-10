<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YandexAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.yandex.client_id' => 'test-client',
            'services.yandex.client_secret' => 'test-secret',
            'services.yandex.redirect' => 'https://shop.test/auth/yandex/callback',
        ]);
        Http::preventStrayRequests();
    }

    public function test_start_redirects_to_yandex_with_state_and_pkce(): void
    {
        $response = $this->get(route('auth.yandex'));
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        $response->assertRedirectContains('https://oauth.yandex.ru/authorize?');
        $this->assertSame('test-client', $query['client_id']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame(session('yandex_oauth.state'), $query['state']);
        $this->assertSame(rtrim(strtr(base64_encode(hash('sha256', session('yandex_oauth.verifier'), true)), '+/', '-_'), '='), $query['code_challenge']);
    }

    public function test_new_yandex_user_is_registered_and_logged_in(): void
    {
        $this->fakeYandex();
        $this->get(route('auth.yandex'));
        $state = session('yandex_oauth.state');

        $this->get(route('auth.yandex.callback', ['state' => $state, 'code' => 'code']))->assertRedirect(route('account'));

        $customer = Customer::query()->sole();
        $this->assertSame('12345', $customer->yandex_id);
        $this->assertSame('user@example.com', $customer->email);
        $this->assertAuthenticatedAs($customer);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://login.yandex.ru/info?format=json'
            && $request->hasHeader('Authorization', 'OAuth test-token'));
    }

    public function test_previously_linked_user_can_sign_in_without_an_email(): void
    {
        $customer = Customer::factory()->create();
        $customer->forceFill(['yandex_id' => '12345'])->save();
        $this->fakeYandex(['id' => '12345', 'client_id' => 'test-client']);
        $this->get(route('auth.yandex'));

        $this->get(route('auth.yandex.callback', ['state' => session('yandex_oauth.state'), 'code' => 'code']))->assertRedirect(route('account'));

        $this->assertAuthenticatedAs($customer);
    }

    public function test_invalid_state_does_not_authenticate_or_call_yandex(): void
    {
        Http::fake();
        $this->get(route('auth.yandex'));

        $this->get(route('auth.yandex.callback', ['state' => 'wrong', 'code' => 'code']))->assertSessionHasErrors('yandex');

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_callback_cannot_be_replayed(): void
    {
        $this->fakeYandex();
        $this->get(route('auth.yandex'));
        $state = session('yandex_oauth.state');
        $this->get(route('auth.yandex.callback', ['state' => $state, 'code' => 'code']));
        $this->post(route('logout'));

        $this->get(route('auth.yandex.callback', ['state' => $state, 'code' => 'code']))->assertSessionHasErrors('yandex');

        $this->assertGuest();
        $this->assertSame(1, Customer::count());
    }

    public function test_existing_email_is_not_automatically_linked(): void
    {
        $customer = Customer::factory()->create(['email' => 'user@example.com']);
        $this->fakeYandex();
        $this->get(route('auth.yandex'));

        $this->get(route('auth.yandex.callback', ['state' => session('yandex_oauth.state'), 'code' => 'code']))->assertSessionHasErrors('yandex');

        $this->assertGuest();
        $this->assertNull($customer->fresh()->yandex_id);
        $this->assertSame(1, Customer::count());
    }

    public function test_authenticated_customer_can_link_yandex(): void
    {
        $customer = Customer::factory()->create();
        $this->fakeYandex();
        $this->actingAs($customer)->post(route('auth.yandex.link'));

        $this->get(route('auth.yandex.callback', ['state' => session('yandex_oauth.state'), 'code' => 'code']))
            ->assertRedirect(route('account'))->assertSessionHas('status');

        $this->assertSame('12345', $customer->fresh()->yandex_id);
        $this->assertAuthenticatedAs($customer);
    }

    public function test_yandex_id_cannot_be_linked_to_two_customers(): void
    {
        $owner = Customer::factory()->create();
        $owner->forceFill(['yandex_id' => '12345'])->save();
        $other = Customer::factory()->create();
        $this->fakeYandex();
        $this->actingAs($other)->post(route('auth.yandex.link'));

        $this->get(route('auth.yandex.callback', ['state' => session('yandex_oauth.state'), 'code' => 'code']))
            ->assertSessionHasErrors('yandex');

        $this->assertNull($other->fresh()->yandex_id);
        $this->assertSame('12345', $owner->fresh()->yandex_id);
    }

    public function test_wrong_yandex_client_id_is_rejected(): void
    {
        $this->fakeYandex(['id' => '12345', 'client_id' => 'another-client', 'default_email' => 'user@example.com']);
        $this->get(route('auth.yandex'));

        $this->get(route('auth.yandex.callback', ['state' => session('yandex_oauth.state'), 'code' => 'code']))->assertSessionHasErrors('yandex');

        $this->assertGuest();
        $this->assertSame(0, Customer::count());
    }

    public function test_expired_authorization_is_rejected(): void
    {
        Http::fake();
        $this->get(route('auth.yandex'));
        $state = session('yandex_oauth.state');
        $this->travel(11)->minutes();

        $this->get(route('auth.yandex.callback', ['state' => $state, 'code' => 'code']))->assertSessionHasErrors('yandex');

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_new_customer_needs_email_from_yandex(): void
    {
        $this->fakeYandex(['id' => '12345', 'client_id' => 'test-client']);
        $this->get(route('auth.yandex'));

        $this->get(route('auth.yandex.callback', ['state' => session('yandex_oauth.state'), 'code' => 'code']))->assertSessionHasErrors('yandex');

        $this->assertGuest();
        $this->assertSame(0, Customer::count());
    }

    public function test_yandex_error_does_not_authenticate_customer(): void
    {
        Http::fake(['https://oauth.yandex.ru/token' => Http::response([], 500)]);
        $this->get(route('auth.yandex'));

        $this->get(route('auth.yandex.callback', ['state' => session('yandex_oauth.state'), 'code' => 'code']))->assertSessionHasErrors('yandex');

        $this->assertGuest();
        $this->assertSame(0, Customer::count());
    }

    public function test_link_cannot_complete_after_switching_customers(): void
    {
        $original = Customer::factory()->create();
        $other = Customer::factory()->create();
        Http::fake();
        $this->actingAs($original)->post(route('auth.yandex.link'));
        $state = session('yandex_oauth.state');

        $this->actingAs($other)->get(route('auth.yandex.callback', ['state' => $state, 'code' => 'code']))->assertSessionHasErrors('yandex');

        $this->assertNull($original->fresh()->yandex_id);
        $this->assertNull($other->fresh()->yandex_id);
        Http::assertNothingSent();
    }

    public function test_yandex_login_is_hidden_until_configured(): void
    {
        config(['services.yandex.client_id' => null]);

        $this->get(route('login'))->assertDontSee('Войти с Яндекс ID');
        $this->get(route('auth.yandex'))->assertNotFound();
    }

    public function test_link_requires_authentication(): void
    {
        $this->post(route('auth.yandex.link'))->assertRedirect(route('login'));
    }

    /**
     * @param  array<string, string>|null  $profile
     */
    private function fakeYandex(?array $profile = null): void
    {
        Http::fake([
            'https://oauth.yandex.ru/token' => Http::response(['access_token' => 'test-token']),
            'https://login.yandex.ru/info*' => Http::response($profile ?? [
                'id' => '12345',
                'client_id' => 'test-client',
                'default_email' => 'User@example.com',
                'real_name' => 'Покупатель',
            ]),
        ]);
    }
}
