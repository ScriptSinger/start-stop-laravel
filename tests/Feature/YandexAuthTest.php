<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_existing_email_signs_in_to_separate_account_without_exposing_old_orders(): void
    {
        $customer = Customer::factory()->create(['email' => 'user@example.com']);
        $oldOrder = Order::query()->create(['customer_id' => $customer->id, 'customer_name' => 'Старый покупатель', 'status' => 'new', 'total' => 100]);
        $this->fakeYandex();
        $this->get(route('auth.yandex'));

        $this->get(route('auth.yandex.callback', ['state' => session('yandex_oauth.state'), 'code' => 'code']))->assertRedirect(route('account'));

        $yandexCustomer = auth()->user();
        $this->assertNotSame($customer->id, $yandexCustomer->id);
        $this->assertNull($yandexCustomer->email);
        $this->assertSame('user@example.com', $yandexCustomer->yandex_email);
        $this->assertNull($customer->fresh()->yandex_id);
        $this->assertSame(2, Customer::count());
        $this->get(route('account.orders'))->assertDontSee('#'.$oldOrder->id);
        $this->get(route('account.order', $oldOrder->id))->assertNotFound();
        $this->get(route('account'))->assertSee('Показать прежние заказы');
    }

    public function test_old_password_merges_orders_and_keeps_yandex_login_on_original_account(): void
    {
        $original = Customer::factory()->legacyPassword('old-pass')->create(['email' => 'user@example.com']);
        $oldOrder = Order::query()->create(['customer_id' => $original->id, 'customer_name' => 'Старый покупатель', 'status' => 'new', 'total' => 100]);
        $this->fakeYandex();
        $this->get(route('auth.yandex'));
        $this->get(route('auth.yandex.callback', ['state' => session('yandex_oauth.state'), 'code' => 'code']));
        $yandexCustomer = auth()->user();
        $yandexCustomer->forceFill(['vk_id' => 'linked-vk'])->save();
        $newOrder = Order::query()->create(['customer_id' => $yandexCustomer->id, 'customer_name' => 'Новый покупатель', 'status' => 'new', 'total' => 200]);

        $this->post(route('auth.yandex.merge'), ['password' => 'old-pass'])
            ->assertRedirect(route('account'))->assertSessionHas('status');

        $this->assertAuthenticatedAs($original);
        $this->assertSame('12345', $original->fresh()->yandex_id);
        $this->assertSame('linked-vk', $original->fresh()->vk_id);
        $this->assertTrue(Hash::check('old-pass', $original->fresh()->password));
        $this->assertNull($original->fresh()->legacy_password_hash);
        $this->assertNull(Customer::query()->find($yandexCustomer->id));
        $this->assertSame($original->id, $oldOrder->fresh()->customer_id);
        $this->assertSame($original->id, $newOrder->fresh()->customer_id);
        $this->get(route('account.orders'))->assertSee('#'.$oldOrder->id)->assertSee('#'.$newOrder->id);

        $this->post(route('logout'));
        $this->fakeYandex(['id' => '12345', 'client_id' => 'test-client']);
        $this->get(route('auth.yandex'));
        $this->get(route('auth.yandex.callback', ['state' => session('yandex_oauth.state'), 'code' => 'code']))->assertRedirect(route('account'));
        $this->assertAuthenticatedAs($original);
    }

    public function test_wrong_old_password_does_not_expose_orders_or_link_accounts(): void
    {
        $original = Customer::factory()->create(['email' => 'user@example.com']);
        $this->fakeYandex();
        $this->get(route('auth.yandex'));
        $this->get(route('auth.yandex.callback', ['state' => session('yandex_oauth.state'), 'code' => 'code']));
        $yandexCustomer = auth()->user();

        $this->post(route('auth.yandex.merge'), ['password' => 'wrong'])->assertSessionHasErrors('password');

        $this->assertAuthenticatedAs($yandexCustomer);
        $this->assertNull($original->fresh()->yandex_id);
        $this->assertSame(2, Customer::count());
    }

    public function test_merge_cannot_take_over_account_linked_to_another_yandex_id(): void
    {
        $original = Customer::factory()->create(['email' => 'user@example.com']);
        $original->forceFill(['yandex_id' => 'other-id'])->save();
        $this->fakeYandex();
        $this->get(route('auth.yandex'));
        $this->get(route('auth.yandex.callback', ['state' => session('yandex_oauth.state'), 'code' => 'code']));
        $yandexCustomer = auth()->user();

        $this->post(route('auth.yandex.merge'), ['password' => 'password'])->assertSessionHasErrors('password');

        $this->assertAuthenticatedAs($yandexCustomer);
        $this->assertSame('other-id', $original->fresh()->yandex_id);
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

        $this->get(route('login'))->assertDontSee('Яндекс ID');
        $this->get(route('register'))->assertDontSee('Яндекс ID');
        $this->get(route('auth.yandex'))->assertNotFound();
    }

    public function test_registration_offers_yandex_login_when_configured(): void
    {
        $this->get(route('register'))
            ->assertSeeInOrder(['id="register-name"', 'Или зарегистрироваться через', 'Яндекс ID'], false)
            ->assertSee('href="'.route('auth.yandex').'"', false);
    }

    public function test_login_offers_yandex_below_password_form_when_configured(): void
    {
        $this->get(route('login'))
            ->assertSeeInOrder(['id="login-email-page"', 'Или войти через', 'Яндекс ID'], false);

        $this->get(route('login'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertSeeInOrder(['id="login-email-modal"', 'Или войти через', 'Яндекс ID'], false);
    }

    public function test_link_requires_authentication(): void
    {
        $this->post(route('auth.yandex.link'))->assertRedirect(route('login'));
    }

    public function test_merge_requires_authentication(): void
    {
        $this->post(route('auth.yandex.merge'), ['password' => 'password'])->assertRedirect(route('login'));
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
