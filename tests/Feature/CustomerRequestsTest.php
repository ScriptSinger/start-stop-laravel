<?php

namespace Tests\Feature;

use App\Enums\CustomerRequestType;
use App\Enums\OrderStatus;
use App\Models\CustomerRequest;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class CustomerRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_form_in_modal_creates_request(): void
    {
        $this->get(route('callback.create'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertDontSee('<html', false)
            ->assertSee('x-data="ajaxForm"', false);

        $this->postJson(route('callback.store'), ['name' => 'Анна', 'phone' => '+7 (987) 000-00-00', 'comment' => 'Подобрать АКБ'])
            ->assertOk()
            ->assertJsonPath('message', 'Спасибо! Мы перезвоним вам в рабочее время.');

        $request = CustomerRequest::query()->sole();
        $this->assertSame(CustomerRequestType::Callback, $request->type);
        $this->assertSame('Подобрать АКБ', $request->comment);
        $this->assertFalse($request->is_processed);
    }

    public function test_callback_validation_errors_are_returned_for_fields(): void
    {
        $this->postJson(route('callback.store'), ['name' => '', 'phone' => '12'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name' => 'Укажите имя.',
                'phone' => 'Проверьте номер телефона: нужно 10–11 цифр.',
            ]);

        $this->assertSame(0, CustomerRequest::query()->count());
    }

    public function test_callback_works_without_javascript(): void
    {
        $this->get(route('callback.create'))->assertOk()->assertSee('<html', false)->assertSee('Заказать звонок');

        $this->post(route('callback.store'), ['name' => 'Анна', 'phone' => '89870000000'])
            ->assertRedirect(route('callback.create'));

        $this->get(route('callback.create'))->assertSee('Спасибо! Мы перезвоним вам в рабочее время.');
    }

    public function test_quick_order_creates_order_for_one_product(): void
    {
        $product = Product::query()->create(['name' => 'TITAN 60Ah', 'slug' => 'titan', 'price' => 7000, 'trade_in_discount' => 1000, 'status' => true]);

        $this->postJson(route('quick-order.store', $product), ['name' => 'Иван', 'phone' => '+7 (987) 000-00-00', 'quantity' => 2, 'trade_in' => 1, 'comment' => 'После 18:00'])
            ->assertOk()
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'принят'));

        $order = Order::query()->with('items')->sole();
        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertNull($order->delivery_method);
        $this->assertNull($order->payment_method);
        $this->assertSame('Быстрый заказ. После 18:00', $order->comment);
        $this->assertEquals(12000, $order->total);
        $this->assertEquals(1000, $order->items->sole()->trade_in_discount);
    }

    public function test_quick_order_is_not_available_for_inactive_product(): void
    {
        $product = Product::query()->create(['name' => 'Архив', 'slug' => 'archive', 'price' => 1, 'status' => false]);

        $this->get(route('quick-order.create', $product))->assertNotFound();
        $this->postJson(route('quick-order.store', $product), ['name' => 'Иван', 'phone' => '89870000000'])->assertNotFound();
    }

    public function test_new_requests_are_counted_on_dashboard_and_cannot_be_created_in_admin(): void
    {
        CustomerRequest::query()->create(['type' => CustomerRequestType::Callback, 'name' => 'Новая Анна', 'phone' => '89870000000']);
        CustomerRequest::query()->create(['type' => CustomerRequestType::Question, 'name' => 'Старый Пётр', 'phone' => '89870000001', 'is_processed' => true]);

        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]), 'moonshine');

        // Список заявок — в разделе «Заявки»; на панели число, в меню — счётчик новых.
        $this->get('/admin')
            ->assertOk()
            ->assertSee('Заявки — перезвонить')
            ->assertSeeInOrder(['Заявки', '>1<'], false);

        $this->get('/admin/resource/customer-request-resource/customer-request-index-page')->assertOk();
        $this->get('/admin/resource/customer-request-resource/customer-request-form-page')->assertForbidden();
    }

    public function test_admin_filters_and_sorts_requests(): void
    {
        CustomerRequest::query()->create(['type' => CustomerRequestType::Callback, 'name' => 'Анна Звонкова', 'phone' => '+7 (919) 152-50-00']);
        CustomerRequest::query()->create(['type' => CustomerRequestType::Question, 'name' => 'Пётр Вопросов', 'phone' => '89870000001']);

        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]), 'moonshine');

        $table = '/admin/component/customer-request-index-page/customer-request-resource?_component_name=index-table-customer-request-resource';

        $this->get($table)->assertOk()->assertSee('badge-blue', false)->assertSee('badge-purple', false);
        $this->get($table.'&filter[phone]=89191525000')->assertSee('Анна Звонкова')->assertDontSee('Пётр Вопросов');
        $this->get($table.'&filter[phone]=152-50')->assertSee('Анна Звонкова')->assertDontSee('Пётр Вопросов');
        $this->get($table.'&filter[name]=Вопрос')->assertSee('Пётр Вопросов')->assertDontSee('Анна Звонкова');
        $this->get($table.'&sort=name')->assertSeeInOrder(['Анна Звонкова', 'Пётр Вопросов']);
        $this->get($table.'&sort=-name')->assertSeeInOrder(['Пётр Вопросов', 'Анна Звонкова']);
    }
}
