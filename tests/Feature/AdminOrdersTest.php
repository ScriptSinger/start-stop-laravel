<?php

namespace Tests\Feature;

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

        $this->assertSame('В обработке', $this->order->refresh()->status);
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
