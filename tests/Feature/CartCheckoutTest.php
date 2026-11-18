<?php

namespace Tests\Feature;

use App\Actions\MergeSocialCustomer;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class CartCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Product $battery;

    private Product $terminal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->battery = Product::query()->create([
            'name' => 'TITAN 60Ah О.П.',
            'slug' => 'titan-60',
            'price' => 7000,
            'quantity' => 3,
            'trade_in_discount' => 1000,
            'status' => true,
        ]);

        $this->terminal = Product::query()->create([
            'name' => 'Клемма "+"',
            'slug' => 'klemma',
            'price' => 500,
            'quantity' => 0,
            'status' => true,
        ]);
    }

    public function test_add_with_trade_in_shows_discounted_price_and_header_count(): void
    {
        $this->post(route('cart.store', $this->battery), ['quantity' => 2, 'trade_in' => 1])
            ->assertRedirect(route('cart.index'));

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('TITAN 60Ah О.П.')
            ->assertSee('6 000 ₽')   // 7000 − 1000 за единицу
            ->assertSee('12 000 ₽')  // × 2
            ->assertSee('name="trade_in" value="1" checked', false);

        $this->get(route('home'))->assertSeeInOrder(['title="Корзина"', '>2</span>'], false);
    }

    public function test_trade_in_cannot_discount_a_product_without_a_trade_in_offer(): void
    {
        $this->post(route('cart.store', $this->terminal), ['quantity' => 2, 'trade_in' => 1]);

        $this->get(route('cart.index'))
            ->assertSee('1 000 ₽')
            ->assertDontSee('name="trade_in" value="1" checked', false);

        $this->checkout(['delivery' => 'pickup', 'payment' => 'cash'])->assertRedirect(route('checkout.success'));
        $order = Order::query()->with('items')->sole();
        $this->assertEquals(1000, $order->total);
        $this->assertNull($order->items->sole()->trade_in_discount);
    }

    public function test_ajax_add_returns_mini_cart_for_modal(): void
    {
        $this->postJson(route('cart.store', $this->battery), ['quantity' => 2])
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('products', [$this->battery->id])
            ->assertJsonPath('html', fn (string $html): bool => str_contains($html, 'TITAN 60Ah О.П.') && str_contains($html, 'Перейти к оформлению заказа'));

        $this->deleteJson(route('cart.destroy', $this->battery))
            ->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonPath('html', fn (string $html): bool => str_contains($html, 'Ваша корзина пуста!'));
    }

    public function test_cart_state_returns_current_contents_without_caching(): void
    {
        $this->post(route('cart.store', $this->battery), ['quantity' => 2]);

        $this->getJson(route('cart.state'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('count', 2)
            ->assertJsonPath('products', [$this->battery->id]);
    }

    public function test_authenticated_cart_survives_a_new_session_and_is_private(): void
    {
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();

        $this->actingAs($customer)->post(route('cart.store', $this->battery), ['quantity' => 2]);
        $this->flushSession();
        $this->actingAs($other)->getJson(route('cart.state'))->assertJsonPath('count', 0);
        $this->flushSession();
        $this->actingAs($customer)->getJson(route('cart.state'))->assertJsonPath('count', 2);
    }

    public function test_guest_cart_is_merged_once_when_customer_signs_in(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer)->post(route('cart.store', $this->battery), ['quantity' => 2]);
        $this->flushSession();
        auth('web')->logout();
        $this->post(route('cart.store', $this->battery), ['quantity' => 3, 'trade_in' => 1]);
        $this->actingAs($customer)->getJson(route('cart.state'))->assertJsonPath('count', 5);
        $this->getJson(route('cart.state'))->assertJsonPath('count', 5);
        $this->get(route('cart.index'))->assertSee('name="trade_in" value="1" checked', false);
    }

    public function test_replayed_guest_session_does_not_merge_twice(): void
    {
        $customer = Customer::factory()->create();
        $guestSession = [
            'cart_guest_token' => 'same-guest-session',
            'cart' => [$this->battery->id => ['quantity' => 3, 'trade_in' => false]],
        ];

        $this->actingAs($customer)->withSession($guestSession)->getJson(route('cart.state'))->assertJsonPath('count', 3);
        $this->withSession($guestSession)->getJson(route('cart.state'))->assertJsonPath('count', 3);
    }

    public function test_guest_merge_caps_quantity_at_the_cart_limit(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer)->post(route('cart.store', $this->battery), ['quantity' => 70]);
        $this->flushSession();
        auth('web')->logout();
        $this->post(route('cart.store', $this->battery), ['quantity' => 70]);
        $this->actingAs($customer)->getJson(route('cart.state'))->assertJsonPath('count', 99);
    }

    public function test_password_login_merges_guest_cart_with_account_cart(): void
    {
        $customer = Customer::factory()->create(['email' => 'cart-login@example.com', 'password' => 'password']);
        $this->actingAs($customer)->post(route('cart.store', $this->battery), ['quantity' => 2]);
        $this->post(route('logout'))->assertRedirect();
        $this->post(route('cart.store', $this->battery), ['quantity' => 3, 'trade_in' => 1]);

        $this->post(route('login'), ['email' => 'cart-login@example.com', 'password' => 'password'])->assertRedirect();
        $this->getJson(route('cart.state'))->assertJsonPath('count', 5);
        $this->get(route('cart.index'))->assertSee('name="trade_in" value="1" checked', false);
    }

    public function test_checkout_clears_only_the_authenticated_customers_cart(): void
    {
        $first = Customer::factory()->create();
        $second = Customer::factory()->create();
        $this->actingAs($first)->post(route('cart.store', $this->battery));
        $this->flushSession();
        $this->actingAs($second)->post(route('cart.store', $this->terminal));
        $this->checkout(['delivery' => 'pickup', 'payment' => 'cash'])->assertRedirect(route('checkout.success'));
        $this->getJson(route('cart.state'))->assertJsonPath('count', 0);
        $this->flushSession();
        $this->actingAs($first)->getJson(route('cart.state'))->assertJsonPath('count', 1);
    }

    public function test_guest_cart_stays_empty_after_customer_logs_out(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer)->post(route('cart.store', $this->battery));
        $this->post(route('logout'))->assertRedirect();
        $this->getJson(route('cart.state'))->assertJsonPath('count', 0);
        $this->actingAs($customer)->getJson(route('cart.state'))->assertJsonPath('count', 1);
    }

    public function test_social_account_merge_preserves_both_customer_carts(): void
    {
        $original = Customer::factory()->create(['email' => 'old-cart@example.com', 'password' => 'password']);
        $social = Customer::factory()->create(['email' => null, 'yandex_id' => 'cart-yandex', 'yandex_email' => 'old-cart@example.com']);
        $this->actingAs($original)->post(route('cart.store', $this->battery), ['quantity' => 2]);
        $this->flushSession();
        $this->actingAs($social)->post(route('cart.store', $this->battery), ['quantity' => 3, 'trade_in' => 1]);

        $merged = app(MergeSocialCustomer::class)->yandex($social, 'password');

        $this->assertNotNull($merged);
        $this->flushSession();
        $this->actingAs($original)->getJson(route('cart.state'))->assertJsonPath('count', 5);
        $this->get(route('cart.index'))->assertSee('name="trade_in" value="1" checked', false);
    }

    public function test_customer_cannot_overwrite_another_devices_newer_cart_line(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer)->post(route('cart.store', $this->battery));
        $this->patchJson(route('cart.update', $this->battery), ['quantity' => 2, 'expected_state' => '1:0'])->assertOk();
        $this->flushSession();
        $this->actingAs($customer)->patchJson(route('cart.update', $this->battery), ['quantity' => 3, 'expected_state' => '1:0'])->assertStatus(409);
        $this->getJson(route('cart.state'))->assertJsonPath('count', 2);
    }

    public function test_second_session_cannot_place_an_order_after_first_session_clears_cart(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer)->post(route('cart.store', $this->battery));
        $this->checkout(['delivery' => 'pickup', 'payment' => 'cash'])->assertRedirect(route('checkout.success'));

        $this->flushSession();
        $this->actingAs($customer);
        $this->checkout(['delivery' => 'pickup', 'payment' => 'cash'])->assertRedirect(route('cart.index'));
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_vk_account_merge_preserves_both_customer_carts(): void
    {
        $original = Customer::factory()->create(['email' => 'old-vk-cart@example.com', 'password' => 'password']);
        $social = Customer::factory()->create(['email' => null, 'vk_id' => 'cart-vk', 'vk_email' => 'old-vk-cart@example.com']);
        $this->actingAs($original)->post(route('cart.store', $this->battery), ['quantity' => 2]);
        $this->flushSession();
        $this->actingAs($social)->post(route('cart.store', $this->terminal));

        $this->assertNotNull(app(MergeSocialCustomer::class)->vk($social, 'password'));
        $this->flushSession();
        $this->actingAs($original)->getJson(route('cart.state'))->assertJsonPath('count', 3);
    }

    public function test_stale_cart_line_cannot_overwrite_or_remove_a_newer_change(): void
    {
        $this->post(route('cart.store', $this->battery));
        $this->patchJson(route('cart.update', $this->battery), ['quantity' => 2, 'expected_state' => '1:0'])->assertOk();

        $this->patchJson(route('cart.update', $this->battery), ['quantity' => 3, 'expected_state' => '1:0'])->assertStatus(409);
        $this->deleteJson(route('cart.destroy', $this->battery), ['expected_state' => '1:0'])->assertStatus(409);
        $this->getJson(route('cart.state'))->assertJsonPath('count', 2);
        $this->deleteJson(route('cart.destroy', $this->battery), ['expected_state' => '2:0'])->assertOk();
        $this->getJson(route('cart.state'))->assertJsonPath('count', 0);
    }

    public function test_header_contains_mini_cart(): void
    {
        $this->post(route('cart.store', $this->battery));

        $this->get(route('home'))
            ->assertSee('data-products="['.$this->battery->id.']"', false)
            ->assertSeeInOrder(['header-cart__dropdown', 'TITAN 60Ah О.П.', 'Перейти к оформлению заказа'], false);
    }

    public function test_adding_again_sums_quantity_and_clamps_it(): void
    {
        $this->post(route('cart.store', $this->terminal), ['quantity' => 60]);
        $this->post(route('cart.store', $this->terminal), ['quantity' => 60]);

        $this->get(route('cart.index'))->assertSee('value="99"', false);
    }

    public function test_update_and_remove(): void
    {
        $this->post(route('cart.store', $this->battery), ['trade_in' => 1]);

        $this->patch(route('cart.update', $this->battery), ['quantity' => 3])->assertRedirect();
        $this->get(route('cart.index'))
            ->assertSee('value="3"', false)
            ->assertSee('21 000 ₽'); // галочку сняли — без скидки

        $this->patch(route('cart.update', $this->battery), ['quantity' => 'много'])->assertRedirect();
        $this->get(route('cart.index'))->assertSee('value="1"', false);

        $this->patch(route('cart.update', $this->battery), ['quantity' => 0]);
        $this->get(route('cart.index'))->assertSee('Ваша корзина пуста!');

        $this->post(route('cart.store', $this->battery));
        $this->delete(route('cart.destroy', $this->battery));
        $this->get(route('cart.index'))->assertSee('Ваша корзина пуста!');
    }

    public function test_inactive_products_cannot_be_bought(): void
    {
        $this->post(route('cart.store', $this->battery));
        $this->battery->update(['status' => false]);

        $this->get(route('cart.index'))
            ->assertDontSee(route('product.show', $this->battery), false)
            ->assertSeeInOrder(['title="Корзина"', '>0</span>'], false);
        $this->post(route('cart.store', $this->battery))->assertNotFound();
    }

    public function test_checkout_requires_review_if_a_product_becomes_unavailable(): void
    {
        $this->post(route('cart.store', $this->battery));
        $this->post(route('cart.store', $this->terminal));
        $this->battery->update(['status' => false]);

        $this->checkout(['delivery' => 'pickup', 'payment' => 'cash'])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('status', 'Некоторые товары больше недоступны. Проверьте состав корзины перед оформлением.');

        $this->assertDatabaseCount('orders', 0);
        $this->get(route('cart.index'))->assertSee('Некоторые товары из корзины больше недоступны');
        $this->checkout(['delivery' => 'pickup', 'payment' => 'cash'])->assertRedirect(route('checkout.success'));
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_checkout_requires_review_when_the_price_changes(): void
    {
        $this->post(route('cart.store', $this->battery));
        $this->get(route('cart.index'))->assertOk();
        $this->battery->update(['price' => 8000]);

        $this->checkout(['delivery' => 'pickup', 'payment' => 'cash'])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('status', 'Цена товара изменилась. Проверьте сумму заказа перед оформлением.');
        $this->assertDatabaseCount('orders', 0);
        $this->get(route('cart.index'))->assertSee('Цена товара изменилась')->assertSee('8 000 ₽');
        $this->checkout(['delivery' => 'pickup', 'payment' => 'cash'])->assertRedirect(route('checkout.success'));
        $this->assertEquals(8000, Order::query()->sole()->total);
    }

    public function test_checkout_creates_order_with_server_side_prices(): void
    {
        $this->post(route('cart.store', $this->battery), ['quantity' => 2, 'trade_in' => 1]);
        $this->post(route('cart.store', $this->terminal));

        $this->checkout([
            'delivery' => 'city',
            'address' => 'ул. Ленина, 1',
            'payment' => 'transfer',
            'comment' => 'Позвоните после 18:00',
            // Подмена цены в форме ни на что не влияет.
            'price' => 1,
            'total' => 1,
        ])->assertRedirect(route('checkout.success'));

        $order = Order::query()->with('items')->sole();

        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertSame('Иван', $order->customer_name);
        $this->assertSame('Доставка по городу', $order->delivery_method);
        $this->assertSame('Оплата переводом или по QR-коду', $order->payment_method);
        $this->assertSame('ул. Ленина, 1', $order->shipping_address);
        $this->assertSame('Позвоните после 18:00', $order->comment);
        $this->assertEquals(12500, $order->total);

        $battery = $order->items->firstWhere('product_id', $this->battery->id);
        $this->assertEquals(6000, $battery->price);
        $this->assertEquals(1000, $battery->trade_in_discount);
        $this->assertSame(2, $battery->quantity);
        $this->assertNull($order->items->firstWhere('product_id', $this->terminal->id)->trade_in_discount);

        // Корзина очищена, «Спасибо» переживает обновление страницы.
        $this->get(route('cart.index'))->assertSee('Ваша корзина пуста!');
        $this->get(route('checkout.success'))->assertOk()->assertSee("Ваш заказ #{$order->id} сформирован!");
        $this->get(route('checkout.success'))->assertOk()->assertSee("Ваш заказ #{$order->id} сформирован!");
    }

    public function test_pickup_order_does_not_store_address(): void
    {
        $this->post(route('cart.store', $this->terminal));

        $this->checkout(['delivery' => 'pickup', 'address' => 'ул. Ленина, 1', 'payment' => 'card'])->assertRedirect(route('checkout.success'));

        $order = Order::query()->sole();
        $this->assertNull($order->shipping_address);
        $this->assertStringStartsWith('Самовывоз', $order->delivery_method);
        $this->assertSame('Банковской картой (только самовывоз)', $order->payment_method);
    }

    public function test_checkout_is_on_cart_page_and_needs_agreement(): void
    {
        $this->post(route('cart.store', $this->terminal));

        $this->get(route('checkout.create'))->assertRedirect(route('cart.index'));
        $this->get(route('cart.index'))
            ->assertSeeInOrder(['Контактные данные', 'Способ получения', 'Способы оплаты', 'Ваш заказ', 'Оформить заказ'])
            ->assertSee(route('page.show', 'privacy'), false);

        $this->checkout(['delivery' => 'pickup', 'payment' => 'cash', 'agree' => null])
            ->assertSessionHasErrors(['agree' => 'Подтвердите согласие с политикой безопасности.']);

        $this->checkout(['lastname' => 'Петров', 'delivery' => 'pickup', 'payment' => 'cash'])
            ->assertRedirect(route('checkout.success'));
        $this->assertSame('Иван Петров', Order::query()->sole()->customer_name);
    }

    public function test_validation_rules(): void
    {
        $this->post(route('cart.store', $this->terminal));

        $this->checkout(['name' => '', 'phone' => '123', 'delivery' => 'city', 'payment' => 'card'])
            ->assertSessionHasErrors([
                'name' => 'Укажите имя.',
                'phone' => 'Проверьте номер телефона: нужно 10–11 цифр.',
                'address' => 'Укажите адрес доставки.',
                'payment' => 'Оплата картой доступна только при самовывозе.',
            ]);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_pickup_only_product_forbids_delivery(): void
    {
        $this->terminal->update(['is_pickup_only' => true]);
        $this->post(route('cart.store', $this->terminal));

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('data-pickup-only="1"', false)
            ->assertSee('x-show="!pickupOnly"', false);

        $this->checkout(['delivery' => 'city', 'address' => 'ул. Ленина, 1', 'payment' => 'cash'])
            ->assertSessionHasErrors(['delivery' => 'В корзине есть товар, который можно забрать только самовывозом.']);
    }

    public function test_empty_cart_and_success_without_order_redirect(): void
    {
        $this->get(route('checkout.create'))->assertRedirect(route('cart.index'));
        $this->checkout(['delivery' => 'pickup', 'payment' => 'cash'])->assertRedirect(route('cart.index'));
        $this->get(route('checkout.success'))->assertRedirect(route('home'));
    }

    public function test_admin_sees_delivery_comment_and_trade_in(): void
    {
        $this->post(route('cart.store', $this->battery), ['trade_in' => 1]);
        $this->checkout(['delivery' => 'pickup', 'payment' => 'cash', 'comment' => 'Нужен чек']);
        $order = Order::query()->sole();

        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]), 'moonshine');

        $this->get("/admin/resource/order-resource/order-detail-page/{$order->id}")
            ->assertOk()
            ->assertSee('Новый')
            ->assertSee('Самовывоз')
            ->assertSee('Нужен чек')
            ->assertSee('Скидка за обмен АКБ')
            ->assertSee('1 000 ₽');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function checkout(array $data): TestResponse
    {
        return $this->post(route('checkout.store'), [
            'name' => 'Иван',
            'phone' => '+7 (987) 000-00-00',
            'agree' => 1,
            ...$data,
        ]);
    }
}
