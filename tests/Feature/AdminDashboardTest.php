<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]), 'moonshine');
    }

    public function test_shows_new_orders_with_links_and_skips_processed_ones(): void
    {
        $new = $this->order('Иван Новый', 'new');
        $this->order('Пётр Обработанный', 'Обработано');
        $this->order('Брошенный', 'unknown', createdAt: now()->subDay());
        $this->order('Старый', 'Сделка завершена', createdAt: now()->subDays(30));

        $this->dashboard()
            ->assertSee('Новые заказы — ждут звонка')
            ->assertSee('Иван Новый')
            ->assertDontSee('Пётр Обработанный')
            ->assertSee("/admin/resource/order-resource/order-detail-page/{$new->id}", false);

        $this->assertMetric('Новые заказы', 1);
        // Брошенные оформления (unknown) и старые заказы не считаются.
        $this->assertMetric('Заказов за 7 дней', 2);
    }

    public function test_links_to_active_products_missing_data(): void
    {
        Product::query()->create(['name' => 'Без фото', 'slug' => 'a', 'price' => 1, 'status' => true]);
        Product::query()->create(['name' => 'Неактивный', 'slug' => 'b', 'price' => 1, 'status' => false]);

        $this->dashboard()
            ->assertSee('Без фото: 1')
            ->assertSee('product-index-page?filter%5Bstatus%5D=1&amp;filter%5Bwithout_image%5D=1', false);

        $this->assertMetric('Активных товаров', 1);
    }

    public function test_dashboard_without_orders_shows_empty_table(): void
    {
        $this->dashboard()->assertSee('Записи не найдены');
        $this->assertMetric('Новые заказы', 0);
    }

    private function dashboard(): TestResponse
    {
        return $this->get('/admin')->assertOk();
    }

    private function assertMetric(string $title, int $value): void
    {
        $this->assertMatchesRegularExpression(
            '/report-card-value">\s*'.$value.'\s*<\/div>\s*<h5 class="report-card-title">\s*'.preg_quote($title, '/').'\s*</u',
            $this->dashboard()->getContent(),
        );
    }

    private function order(string $name, string $status, ?\DateTimeInterface $createdAt = null): Order
    {
        $order = Order::query()->create([
            'customer_name' => $name,
            'customer_phone' => '+79870000000',
            'status' => $status,
            'total' => 6000,
        ]);

        if ($createdAt) {
            $order->forceFill(['created_at' => $createdAt])->save();
        }

        return $order;
    }
}
