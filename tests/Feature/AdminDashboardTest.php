<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    public function test_shows_key_numbers_and_menu_badges(): void
    {
        $this->order('Иван Новый', 'new');
        $this->order('Пётр Обработанный', 'Обработано');
        $this->order('Брошенный', 'unknown', createdAt: now()->subDay());
        $this->order('Старый', 'Сделка завершена', createdAt: now()->subDays(45));

        $this->assertMetric('Новые заказы', '1');
        $this->assertMetric('Брошенные оформления, 30 дн.', '1');
        // За 30 дней — новый и обработанный; брошенный не считается, старый — в прошлом периоде.
        $this->assertMetric('Продажи за 30 дн. (было 6 000 ₽)', '12 000 ₽');

        // Таблицы заказов — в разделе «Заказы», на панели их нет; новые видны счётчиком в меню.
        $this->dashboard()
            ->assertDontSee('Иван Новый')
            ->assertSee('1 брошенное оформление — оставили телефон, но не подтвердили заказ: перезвонить')
            ->assertSee('order-index-page?filter%5Bstatus%5D%5B0%5D=unknown', false)
            ->assertSeeInOrder(['Заказы', '>1<'], false);
    }

    public function test_attention_lists_only_problems_that_exist(): void
    {
        Product::query()->create(['name' => 'Без фото', 'slug' => 'a', 'price' => 1, 'status' => true, 'image' => null]);
        Product::query()->create(['name' => 'Неактивный', 'slug' => 'b', 'price' => 1, 'status' => false]);

        $this->dashboard()
            ->assertSee('1 товар без фото →')
            ->assertSee('product-index-page?filter%5Bstatus%5D=1&amp;filter%5Bwithout_image%5D=1', false)
            ->assertDontSee('невидим')
            ->assertDontSee('брошенное оформление');
    }

    public function test_shows_top_assortment_gap(): void
    {
        Cache::put('assortment-gaps', ['cars' => 120, 'without_batteries' => 7, 'sizes' => [
            ['size' => 'Азия B24 (234 x 127 x 227 мм)', 'polarity' => 'Обратная', 'cars' => 5],
        ]]);

        $this->dashboard()->assertSee('Азия B24 (234 x 127 x 227 мм), Обратная — нужен 5 машинам, в ассортименте нет →');
    }

    public function test_empty_shop_is_all_good(): void
    {
        Cache::put('assortment-gaps', ['cars' => 0, 'without_batteries' => 0, 'sizes' => []]);

        $this->dashboard()->assertSee('Всё в порядке');
        $this->assertMetric('Новые заказы', '0');
    }

    private function dashboard(): TestResponse
    {
        return $this->get('/admin')->assertOk();
    }

    private function assertMetric(string $title, string $value): void
    {
        $this->assertMatchesRegularExpression(
            '/report-card-value">\s*'.preg_quote($value, '/').'\s*<\/div>\s*<h5 class="report-card-title">\s*'.preg_quote($title, '/').'\s*</u',
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
