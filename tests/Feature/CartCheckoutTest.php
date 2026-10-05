<?php

namespace Tests\Feature;

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
            ->assertSee('6000р.')   // 7000 − 1000 за единицу
            ->assertSee('12000р.')  // × 2
            ->assertSee('name="trade_in" value="1" checked', false);

        $this->get(route('home'))->assertSeeInOrder(['title="Корзина"', '>2</span>'], false);
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
            ->assertSee('21000р.'); // галочку сняли — без скидки

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

        $this->assertSame('new', $order->status);
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
            ->assertDontSee('data-delivery="city"', false);

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
