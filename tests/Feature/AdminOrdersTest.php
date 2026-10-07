<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminOrdersTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($admin, 'moonshine');

        $customer = Customer::query()->create(['name' => 'Игорь Петров', 'email' => 'igor@example.com', 'phone' => '+79870000000']);
        $product = Product::query()->create(['name' => 'TITAN 60Ah О.П.', 'slug' => 'titan-60ah-op', 'price' => 5200]);

        $this->order = Order::query()->create([
            'customer_id' => $customer->id,
            'customer_name' => 'Игорь Петров',
            'customer_phone' => '+79870000000',
            'status' => 'Ожидание',
            'payment_method' => 'Оплата наличными',
            'total' => 5200,
        ]);

        $this->order->items()->create([
            'product_id' => $product->id,
            'name' => 'TITAN 60Ah О.П.',
            'price' => 5200,
            'quantity' => 1,
            'total' => 5200,
        ]);

        Page::query()->create(['title' => 'О компании', 'slug' => 'o-kompanii']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function indexPages(): array
    {
        return [
            'заказы' => ['/admin/resource/order-resource/order-index-page', 'Заказы'],
            'клиенты' => ['/admin/resource/customer-resource/customer-index-page', 'Клиенты'],
            'страницы' => ['/admin/resource/page-resource/page-index-page', 'Страницы'],
        ];
    }

    #[DataProvider('indexPages')]
    public function test_index_page_renders(string $url, string $title): void
    {
        $this->get($url)->assertOk()->assertSee($title);
    }

    public function test_order_status_is_shown_as_coloured_badge(): void
    {
        $this->get('/admin/component/order-index-page/order-resource?_component_name=index-table-order-resource')
            ->assertOk()
            ->assertSee('badge-yellow', false)
            ->assertSee('Ожидание');
    }

    public function test_orders_filter_by_delivery_and_payment(): void
    {
        $this->order->update(['delivery_method' => 'Бесплатная доставка', 'payment_method' => 'Оплата при доставке']);
        Order::query()->create([
            'customer_name' => 'Самовывозов',
            'status' => OrderStatus::New,
            'delivery_method' => 'Самовывоз: Магазин "СТАРТ-СТОП", Время работы: 10-20',
            'payment_method' => 'Банковской картой (Только самовывоз)',
            'total' => 4900,
        ]);

        $table = '/admin/component/order-index-page/order-resource?_component_name=index-table-order-resource';

        $this->get($table.'&filter[delivery_method]=pickup')->assertSee('Самовывозов')->assertDontSee('Игорь Петров');
        $this->get($table.'&filter[delivery_method]=city')->assertSee('Игорь Петров')->assertDontSee('Самовывозов');
        $this->get($table.'&filter[payment_method]=cash')->assertSee('Игорь Петров')->assertDontSee('Самовывозов');
        $this->get($table.'&filter[payment_method]=card')->assertSee('Самовывозов')->assertDontSee('Игорь Петров');
    }

    public function test_orders_sort_by_status_in_workflow_order(): void
    {
        Order::query()->create(['customer_name' => 'Новиков', 'status' => OrderStatus::New, 'total' => 1]);
        Order::query()->create(['customer_name' => 'Отменов', 'status' => OrderStatus::Cancelled, 'total' => 1]);

        $this->get('/admin/component/order-index-page/order-resource?_component_name=index-table-order-resource&sort=status')
            ->assertOk()
            ->assertSeeInOrder(['Новиков', 'Игорь Петров', 'Отменов']);
    }

    public function test_customers_list_shows_purchases_and_filters_by_phone_digits(): void
    {
        Order::query()->create(['customer_id' => $this->order->customer_id, 'customer_name' => 'Игорь Петров', 'status' => OrderStatus::Cancelled, 'total' => 9999]);
        Customer::query()->create(['name' => 'Без Заказов', 'email' => 'empty@example.com', 'phone' => '+7 (917) 000-11-22']);

        $table = '/admin/component/customer-index-page/customer-resource?_component_name=index-table-customer-resource';

        $this->get($table)->assertOk()->assertSee('5 200')->assertDontSee('15 199');
        $this->get($table.'&filter[phone]=89870000000')->assertSee('Игорь Петров')->assertDontSee('Без Заказов');
        $this->get($table.'&filter[has_orders]=0')->assertSee('Без Заказов')->assertDontSee('Игорь Петров');
        $this->get($table.'&sort=-orders_sum_total')->assertSeeInOrder(['Игорь Петров', 'Без Заказов']);
    }

    public function test_order_form_offers_delivery_and_payment_choices(): void
    {
        $this->order->update(['delivery_method' => 'Бесплатная доставка']);

        $this->get("/admin/resource/order-resource/order-form-page/{$this->order->id}")
            ->assertOk()
            ->assertSee('Доставка по городу')
            ->assertSee('Бесплатная доставка')
            ->assertSee('Оплата переводом или по QR-коду');
    }

    public function test_legacy_statuses_map_to_known_ones(): void
    {
        $this->assertSame(OrderStatus::Pending, OrderStatus::fromLegacy('Ожидание'));
        $this->assertSame(OrderStatus::Completed, OrderStatus::fromLegacy('Доставлено'));
        $this->assertSame(OrderStatus::Cancelled, OrderStatus::fromLegacy('Возврат'));
        $this->assertSame(OrderStatus::Abandoned, OrderStatus::fromLegacy(null));
    }

    public function test_order_detail_shows_items(): void
    {
        $this->get("/admin/resource/order-resource/order-detail-page/{$this->order->id}")
            ->assertOk()
            ->assertSee('Игорь Петров')
            ->assertSee('Позиции')
            ->assertSee('TITAN 60Ah О.П.');
    }

    public function test_order_status_can_be_changed(): void
    {
        $this->put("/admin/resource/order-resource/crud/{$this->order->id}", [
            'status' => 'В обработке',
            'customer_name' => 'Игорь Петров',
            'customer_phone' => '+79870000000',
            'payment_method' => 'Оплата наличными',
            'total' => 5200,
        ])->assertRedirect();

        $this->assertSame(OrderStatus::Processing, $this->order->refresh()->status);
        $this->assertSame(1, $this->order->items()->count());
    }

    public function test_orders_cannot_be_created_and_items_are_read_only(): void
    {
        $this->get('/admin/resource/order-resource/order-form-page')->assertForbidden();
        $this->post('/admin/resource/order-resource/crud', ['customer_name' => 'X'])->assertForbidden();

        $item = $this->order->items()->firstOrFail();
        $this->delete("/admin/resource/order-item-resource/crud/{$item->id}")->assertForbidden();
        $this->assertSame(1, $this->order->items()->count());
    }

    public function test_customer_list_shows_orders_count(): void
    {
        $this->get('/admin/component/customer-index-page/customer-resource?_component_name=index-table-customer-resource')
            ->assertOk()
            ->assertSee('Игорь Петров')
            ->assertSee('igor@example.com');
    }
}
