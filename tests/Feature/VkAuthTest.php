<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class VkAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.vkid.client_id' => '123456',
            'services.vkid.redirect' => 'https://shop.test/auth/vkid/callback',
        ]);
        Http::preventStrayRequests();
    }

    public function test_start_redirects_to_vk_with_state_and_pkce(): void
    {
        $response = $this->get(route('auth.vkid'));
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        $response->assertRedirectContains('https://id.vk.ru/authorize?');
        $this->assertSame('123456', $query['client_id']);
        $this->assertSame('email', $query['scope']);
        $this->assertSame('s256', $query['code_challenge_method']);
        $this->assertSame(session('vkid_oauth.state'), $query['state']);
        $this->assertSame(rtrim(strtr(base64_encode(hash('sha256', session('vkid_oauth.verifier'), true)), '+/', '-_'), '='), $query['code_challenge']);
    }

    public function test_new_vk_user_is_registered_and_logged_in(): void
    {
        $this->fakeVk();
        $this->get(route('auth.vkid'));

        $this->returnFromVk()->assertRedirect(route('account'));

        $customer = Customer::query()->sole();
        $this->assertAuthenticatedAs($customer);
        $this->assertSame('12345', $customer->vk_id);
        $this->assertSame('user@example.com', $customer->email);
        $this->assertSame('Иван Иванов', $customer->name);
        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://id.vk.ru/oauth2/auth?')
            && $request['code'] === 'code' && str_contains($request->url(), 'device_id=device-1'));
        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://id.vk.ru/oauth2/user_info?')
            && $request['access_token'] === 'token');
    }

    public function test_existing_email_stays_separate_until_password_proves_ownership(): void
    {
        $original = Customer::factory()->legacyPassword('old-pass')->create(['email' => 'user@example.com']);
        $oldOrder = Order::query()->create(['customer_id' => $original->id, 'customer_name' => 'Старый покупатель', 'status' => 'new', 'total' => 100]);
        $this->fakeVk();
        $this->get(route('auth.vkid'));
        $this->returnFromVk()->assertRedirect(route('account'));

        $vkCustomer = auth()->user();
        $vkCustomer->forceFill(['yandex_id' => 'linked-yandex'])->save();
        $this->assertNotSame($original->id, $vkCustomer->id);
        $this->assertNull($vkCustomer->email);
        $this->assertSame('user@example.com', $vkCustomer->vk_email);
        $this->get(route('account'))->assertSee(route('auth.vkid.merge'), false);
        $this->get(route('account.order', $oldOrder->id))->assertNotFound();
        $this->post(route('auth.vkid.merge'), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertAuthenticatedAs($vkCustomer);

        $newOrder = Order::query()->create(['customer_id' => $vkCustomer->id, 'customer_name' => 'Новый покупатель', 'status' => 'new', 'total' => 200]);
        $this->post(route('auth.vkid.merge'), ['password' => 'old-pass'])
            ->assertRedirect(route('account'))->assertSessionHas('status');

        $this->assertAuthenticatedAs($original);
        $this->assertSame('12345', $original->fresh()->vk_id);
        $this->assertSame('linked-yandex', $original->fresh()->yandex_id);
        $this->assertSame($original->id, $oldOrder->fresh()->customer_id);
        $this->assertSame($original->id, $newOrder->fresh()->customer_id);
        $this->assertNull(Customer::query()->find($vkCustomer->id));
    }

    public function test_previously_linked_vk_user_can_sign_in_without_email(): void
    {
        $customer = Customer::factory()->create();
        $customer->forceFill(['vk_id' => '12345'])->save();
        $this->fakeVk(['user_id' => '12345']);
        $this->get(route('auth.vkid'));

        $this->returnFromVk()->assertRedirect(route('account'));

        $this->assertAuthenticatedAs($customer);
    }

    public function test_new_vk_user_without_email_can_still_shop(): void
    {
        $this->fakeVk(['user_id' => '12345', 'first_name' => 'Иван']);
        $this->get(route('auth.vkid'));

        $this->returnFromVk()->assertRedirect(route('account'));

        $customer = Customer::query()->sole();
        $this->assertAuthenticatedAs($customer);
        $this->assertNull($customer->email);
        $this->get(route('account'))->assertOk();
    }

    public function test_invalid_or_replayed_state_cannot_authenticate(): void
    {
        Http::fake();
        $this->get(route('auth.vkid'));
        $this->get(route('auth.vkid.callback', ['state' => 'wrong', 'code' => 'code', 'device_id' => 'device-1']))
            ->assertSessionHasErrors('vkid');

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_vk_callback_cannot_be_replayed_after_logout(): void
    {
        $this->fakeVk();
        $this->get(route('auth.vkid'));
        $state = session('vkid_oauth.state');
        $this->returnFromVk()->assertRedirect(route('account'));
        $this->post(route('logout'));

        $this->get(route('auth.vkid.callback', ['state' => $state, 'code' => 'code', 'device_id' => 'device-1']))
            ->assertSessionHasErrors('vkid');

        $this->assertGuest();
        $this->assertSame(1, Customer::count());
    }

    public function test_callback_without_device_id_does_not_request_token(): void
    {
        Http::fake();
        $this->get(route('auth.vkid'));
        $this->get(route('auth.vkid.callback', ['state' => session('vkid_oauth.state'), 'code' => 'code']))
            ->assertSessionHasErrors('vkid');

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_expired_vk_authorization_is_rejected(): void
    {
        Http::fake();
        $this->get(route('auth.vkid'));
        $this->travel(11)->minutes();

        $this->returnFromVk()->assertSessionHasErrors('vkid');

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_token_state_and_user_id_must_match_vk_profile(): void
    {
        Http::fake([
            'https://id.vk.ru/oauth2/auth*' => Http::response(['access_token' => 'token', 'state' => 'wrong', 'user_id' => 12345]),
            'https://id.vk.ru/oauth2/user_info*' => Http::response(['user' => ['user_id' => '12345']]),
        ]);
        $this->get(route('auth.vkid'));
        $this->returnFromVk()->assertSessionHasErrors('vkid');
        $this->assertGuest();
        Http::assertSentCount(1);
    }

    public function test_token_and_profile_user_ids_cannot_disagree(): void
    {
        $this->fakeVk(['user_id' => '99999', 'email' => 'user@example.com']);
        $this->get(route('auth.vkid'));

        $this->returnFromVk()->assertSessionHasErrors('vkid');

        $this->assertGuest();
        $this->assertSame(0, Customer::count());
    }

    public function test_authenticated_customer_can_link_vk_and_other_customer_cannot_take_it(): void
    {
        $owner = Customer::factory()->create();
        $this->fakeVk();
        $this->actingAs($owner)->post(route('auth.vkid.link'));
        $this->returnFromVk()->assertRedirect(route('account'))->assertSessionHas('status');
        $this->assertSame('12345', $owner->fresh()->vk_id);

        $other = Customer::factory()->create();
        $this->fakeVk();
        $this->actingAs($other)->post(route('auth.vkid.link'));
        $this->returnFromVk()->assertSessionHasErrors('vkid');
        $this->assertNull($other->fresh()->vk_id);
    }

    public function test_guest_cannot_link_or_merge_vk_account(): void
    {
        $this->post(route('auth.vkid.link'))->assertRedirect(route('login'));
        $this->post(route('auth.vkid.merge'), ['password' => 'password'])->assertRedirect(route('login'));
    }

    public function test_vk_login_is_hidden_until_configured(): void
    {
        config(['services.vkid.client_id' => null]);
        $this->get(route('login'))->assertDontSee('VK ID');
        $this->get(route('auth.vkid'))->assertNotFound();
    }

    public function test_vk_button_is_shown_on_login_and_registration(): void
    {
        $this->get(route('login'))->assertSee(route('auth.vkid'), false);
        $this->get(route('register'))->assertSee('VK ID');
    }

    private function returnFromVk(): TestResponse
    {
        return $this->get(route('auth.vkid.callback', [
            'state' => session('vkid_oauth.state'),
            'code' => 'code',
            'device_id' => 'device-1',
        ]));
    }

    /**
     * @param  array<string, string>|null  $profile
     */
    private function fakeVk(?array $profile = null): void
    {
        Http::fake(function (Request $request) use ($profile) {
            if (str_starts_with($request->url(), 'https://id.vk.ru/oauth2/auth?')) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

                return Http::response([
                    'access_token' => 'token',
                    'state' => $query['state'] ?? null,
                    'user_id' => 12345,
                ]);
            }

            return Http::response(['user' => $profile ?? [
                'user_id' => '12345',
                'first_name' => 'Иван',
                'last_name' => 'Иванов',
                'email' => 'User@example.com',
            ]]);
        });
    }
}
