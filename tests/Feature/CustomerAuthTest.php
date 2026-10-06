<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_customer_and_logs_in(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Иван',
            'email' => 'Ivan@Example.com',
            'phone' => '+7 (987) 000-00-00',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'agree' => 1,
        ])->assertRedirect(route('account'));

        $customer = Customer::query()->sole();
        $this->assertSame('ivan@example.com', $customer->email);
        $this->assertTrue(Hash::check('secret-pass', $customer->password));
        $this->assertAuthenticatedAs($customer);
    }

    public function test_registration_with_existing_email_suggests_password_recovery(): void
    {
        Customer::factory()->legacyPassword('old-pass')->create(['email' => 'old@example.com']);

        $this->post(route('register.store'), [
            'name' => 'Иван', 'email' => 'old@example.com', 'phone' => '+7 (987) 000-00-00',
            'password' => 'secret-pass', 'password_confirmation' => 'secret-pass', 'agree' => 1,
        ])->assertSessionHasErrors(['email' => 'Этот e-mail уже зарегистрирован. Войдите или восстановите пароль.']);

        $this->assertGuest();
    }

    public function test_login_and_logout(): void
    {
        $customer = Customer::factory()->create(['email' => 'ivan@example.com']);

        $this->post(route('login.store'), ['email' => 'ivan@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => 'Неверный e-mail или пароль.']);
        $this->assertGuest();

        $this->post(route('login.store'), ['email' => 'IVAN@example.com', 'password' => 'password'])
            ->assertRedirect(route('account'));
        $this->assertAuthenticatedAs($customer);

        $this->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_login_in_modal_answers_json(): void
    {
        Customer::factory()->create(['email' => 'ivan@example.com']);

        $this->get(route('login'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertDontSee('<html', false)
            ->assertSee('x-data="ajaxForm({reload: true})"', false);

        $this->postJson(route('login.store'), ['email' => 'ivan@example.com', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Неверный e-mail или пароль.');

        $this->postJson(route('login.store'), ['email' => 'ivan@example.com', 'password' => 'password'])->assertOk();
        $this->assertAuthenticated();
    }

    public function test_legacy_customer_logs_in_with_old_password_which_is_rehashed(): void
    {
        $customer = Customer::factory()->legacyPassword('old-pass', 'Ab3dE5gH9')->create(['email' => 'old@example.com']);

        $this->post(route('login.store'), ['email' => 'old@example.com', 'password' => 'nope'])->assertSessionHasErrors('email');

        $this->post(route('login.store'), ['email' => 'old@example.com', 'password' => 'old-pass'])->assertRedirect(route('account'));

        $customer->refresh();
        $this->assertAuthenticatedAs($customer);
        $this->assertTrue(Hash::check('old-pass', $customer->password));
        $this->assertNull($customer->legacy_password_hash);
        $this->assertNull($customer->legacy_password_salt);
    }

    public function test_password_reset_by_email(): void
    {
        Notification::fake();
        $customer = Customer::factory()->legacyPassword('old-pass')->create(['email' => 'old@example.com']);

        $this->post(route('password.email'), ['email' => 'old@example.com'])
            ->assertSessionHas('status', 'Мы отправили ссылку для восстановления пароля на ваш e-mail.');

        $token = null;
        Notification::assertSentTo($customer, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($customer, &$token): bool {
            $mail = $notification->toMail($customer);
            $token = str($mail->actionUrl)->after('reset-password/')->before('?')->toString();

            return $mail->subject === 'Восстановление пароля — '.config('shop.name');
        });

        $this->get(route('password.reset', ['token' => $token, 'email' => 'old@example.com']))->assertOk()->assertSee('Новый пароль');

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'old@example.com',
            'password' => 'new-secret',
            'password_confirmation' => 'new-secret',
        ])->assertRedirect(route('login'));

        $customer->refresh();
        $this->assertTrue(Hash::check('new-secret', $customer->password));
        $this->assertNull($customer->legacy_password_hash);
        $this->assertTrue(Password::broker('customers')->getRepository()->recentlyCreatedToken($customer) === false);
    }

    public function test_account_pages_require_login(): void
    {
        $this->get(route('account'))->assertRedirect(route('login'));
        $this->get(route('account.orders'))->assertRedirect(route('login'));
    }

    public function test_order_history_shows_only_own_visible_orders(): void
    {
        $customer = Customer::factory()->create();
        $stranger = Customer::factory()->create();

        $own = $this->order($customer, 'В обработке', 7000);
        $abandoned = $this->order($customer, 'unknown', 1111);
        $foreign = $this->order($stranger, 'new', 2222);

        $this->actingAs($customer);

        $this->get(route('account'))->assertOk()->assertSee($customer->name);
        $this->get(route('account.orders'))
            ->assertOk()
            ->assertSee('#'.$own->id)
            ->assertSee('В обработке')
            ->assertSee('7000р.')
            ->assertDontSee('1111р.')
            ->assertDontSee('2222р.');

        $this->get(route('account.order', $own->id))->assertOk()->assertSee('Заказ #'.$own->id)->assertSee('TITAN 60');
        $this->get(route('account.order', $abandoned->id))->assertNotFound();
        $this->get(route('account.order', $foreign->id))->assertNotFound();
    }

    public function test_checkout_of_logged_in_customer_goes_to_history(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::query()->create(['name' => 'Клемма', 'slug' => 'klemma', 'price' => 500, 'quantity' => 1, 'status' => true]);

        $this->actingAs($customer)->post(route('cart.store', $product));
        $this->get(route('cart.index'))->assertDontSee('Уже зарегистрированы?');

        $this->post(route('checkout.store'), [
            'name' => 'Иван', 'phone' => '+7 (987) 000-00-00', 'delivery' => 'pickup', 'payment' => 'cash', 'agree' => 1,
        ])->assertRedirect(route('checkout.success'));

        $this->assertSame($customer->id, Order::query()->sole()->customer_id);
    }

    public function test_header_shows_login_for_guests_and_account_for_customers(): void
    {
        $this->get(route('home'))->assertSee('Авторизация')->assertSee(route('register'), false);

        $this->actingAs(Customer::factory()->create())
            ->get(route('home'))
            ->assertSee(route('account.orders'), false)
            ->assertSee(route('logout'), false)
            ->assertDontSee('Авторизация');
    }

    private function order(Customer $customer, string $status, int $total): Order
    {
        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => '+79870000000',
            'status' => $status,
            'total' => $total,
        ]);
        $order->items()->create(['name' => 'TITAN 60', 'price' => $total, 'quantity' => 1, 'total' => $total]);

        return $order;
    }
}
