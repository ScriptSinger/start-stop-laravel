<?php

namespace Tests\Feature;

use App\Enums\CustomerRequestType;
use App\Models\Category;
use App\Models\CustomerRequest;
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

        $this->dashboard()
            ->assertSee('Заказы и заявки за 30 дней')
            ->assertSee('Оформленные заказы')
            ->assertSee('Брошенные оформления')
            ->assertSee('Заявки');

        // Таблицы заказов — в разделе «Заказы», на панели их нет; новые видны счётчиком в меню.
        $this->dashboard()
            ->assertDontSee('Иван Новый')
            ->assertSee('1 брошенное оформление — оставили телефон, но не подтвердили заказ: перезвонить')
            ->assertSee('order-index-page?filter%5Bstatus%5D%5B0%5D=unknown', false)
            ->assertSeeInOrder(['Заказы', '>1<'], false);
    }

    public function test_orders_chart_counts_each_series_by_creation_date_and_current_status(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));
        $this->order('Подтверждённый', 'new');
        $abandoned = $this->order('Брошенный', 'unknown');
        $this->order('Отменённый', 'Отменено');
        $this->order('Вчера', 'Обработано', now()->subDay());
        $this->order('За пределами периода', 'new', now()->subDays(30));
        CustomerRequest::query()->create(['type' => CustomerRequestType::Callback, 'name' => 'Клиент', 'phone' => '89870000000']);

        $series = $this->ordersChartSeries();
        $this->assertSame([1, 1], array_slice($series[0]['data'], -2));
        $this->assertSame([0, 1], array_slice($series[1]['data'], -2));
        $this->assertSame([0, 1], array_slice($series[2]['data'], -2));
        $this->assertSame(2, array_sum($series[0]['data']));

        $abandoned->update(['status' => 'Обработано']);
        $series = $this->ordersChartSeries();
        $this->assertSame(2, end($series[0]['data']));
        $this->assertSame(0, end($series[1]['data']));
    }

    /** @return array<int, array{name: string, data: array<int, int>}> */
    private function ordersChartSeries(): array
    {
        $html = html_entity_decode($this->dashboard()->getContent(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        preg_match("/x-data=\"lineChart\\(\\{\\s*config: JSON\\.parse\\('([^']+)'\\)/", $html, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $json = json_decode('"'.$matches[1].'"', true, flags: JSON_THROW_ON_ERROR);

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR)['series'];
    }

    public function test_catalog_chart_separates_product_availability(): void
    {
        $minimum = (int) config('shop.supplier_order_min_quantity');
        Product::query()->create(['name' => 'Склад', 'slug' => 'stock', 'price' => 100, 'status' => true, 'quantity' => 2, 'supplier_quantity' => $minimum]);
        Product::query()->create(['name' => 'Поставщик', 'slug' => 'supplier', 'price' => 200, 'status' => true, 'quantity' => 0, 'supplier_quantity' => $minimum]);
        Product::query()->create(['name' => 'Скрытый', 'slug' => 'hidden', 'price' => 400, 'status' => false, 'quantity' => 3]);
        $deleted = Product::query()->create(['name' => 'Удалённый', 'slug' => 'deleted', 'price' => 500, 'status' => true, 'quantity' => 10]);
        $deleted->delete();

        $this->dashboard()
            ->assertSee('Структура каталога')
            ->assertSee('Все аккумуляторы')
            ->assertSee('Неактивные — 1')
            ->assertSee('Активные, нет в наличии — 0')
            ->assertSee('Активные, на складе магазина — 1')
            ->assertSee('Активные, под заказ — 1')
            ->assertSee('Заказы и заявки за 30 дней');
    }

    public function test_catalog_chart_fragment_filters_batteries_and_rejects_unknown_scope(): void
    {
        $batteryCategory = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'batteries']);
        config(['shop.battery_fitment.category_ids' => [$batteryCategory->id]]);
        $this->get('/admin/page/dashboard?_fragment-load=catalog-chart&_data%5Bcatalog_scope%5D=batteries')
            ->assertOk()
            ->assertSee('Аккумуляторы')
            ->assertSee('Активные, на складе магазина — 0');

        $battery = Product::query()->create(['name' => 'АКБ', 'slug' => 'battery', 'price' => 100, 'quantity' => 1, 'status' => true]);
        $battery->categories()->attach($batteryCategory);
        Product::query()->create(['name' => 'Другое', 'slug' => 'other', 'price' => 100, 'quantity' => 1, 'status' => true]);

        $this->get('/admin/page/dashboard?_fragment-load=catalog-chart&_data%5Bcatalog_scope%5D=batteries')
            ->assertOk()
            ->assertSee('Аккумуляторы')
            ->assertSee('Активные, на складе магазина — 1')
            ->assertDontSee('Структура каталога');

        $this->get('/admin/page/dashboard?_fragment-load=catalog-chart&_data%5Bcatalog_scope%5D=all')
            ->assertOk()
            ->assertSee('Структура каталога')
            ->assertSee('Активные, на складе магазина — 2');

        $this->get('/admin/page/dashboard?_fragment-load=catalog-chart&_data%5Bcatalog_scope%5D=unknown')
            ->assertOk()
            ->assertSee('Структура каталога')
            ->assertSee('Активные, на складе магазина — 2');
    }

    public function test_all_batteries_include_subcategories_without_double_counting(): void
    {
        $root = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'battery-root']);
        $child = Category::query()->create(['name' => 'Подкатегория АКБ', 'slug' => 'battery-child', 'parent_id' => $root->id, 'status' => false]);
        config(['shop.battery_fitment.category_ids' => [$root->id, $child->id]]);
        $battery = Product::query()->create(['name' => 'АКБ', 'slug' => 'battery-sub', 'price' => 100, 'quantity' => 1, 'status' => true]);
        $battery->categories()->attach($child);
        Product::query()->create(['name' => 'Другое', 'slug' => 'other-sub', 'price' => 100, 'quantity' => 1, 'status' => true]);

        $this->get('/admin/page/dashboard?_fragment-load=catalog-chart&_data%5Bcatalog_scope%5D=batteries')
            ->assertOk()
            ->assertSee('Активные, на складе магазина — 1');
        $this->get('/admin/page/dashboard?_fragment-load=catalog-chart&_data%5Bcatalog_scope%5D=category_'.$root->id)
            ->assertOk()
            ->assertSee('Активные, на складе магазина — 1');
    }

    public function test_catalog_filter_includes_subcategories_without_duplicate_products(): void
    {
        $parent = Category::query()->create(['name' => 'Раздел', 'slug' => 'section', 'status' => true]);
        $child = Category::query()->create(['name' => 'Подраздел', 'slug' => 'subsection', 'status' => false, 'parent_id' => $parent->id]);
        $product = Product::query()->create(['name' => 'Товар', 'slug' => 'item', 'price' => 100, 'quantity' => 1, 'status' => true]);
        $product->categories()->attach([$parent->id, $child->id]);
        Product::query()->create(['name' => 'Другой', 'slug' => 'another', 'price' => 100, 'quantity' => 1, 'status' => true]);

        $this->dashboard()->assertSee('Раздел / Подраздел (неактивная)');
        $this->get('/admin/page/dashboard?_fragment-load=catalog-chart&_data%5Bcatalog_scope%5D=category_'.$parent->id)
            ->assertOk()
            ->assertSee('Выбранная категория')
            ->assertSee('Активные, на складе магазина — 1');
        $this->get('/admin/page/dashboard?_fragment-load=catalog-chart&_data%5Bcatalog_scope%5D=category_999999')
            ->assertOk()
            ->assertSee('Структура каталога')
            ->assertSee('Активные, на складе магазина — 2');
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
        $this->dashboard()->assertSee('Заказы и заявки за 30 дней');
    }

    private function dashboard(): TestResponse
    {
        return $this->get('/admin')->assertOk();
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
