<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Jobs\SyncOrderToMetrika;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MetrikaOrderSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_checkout_forms_save_the_visitors_metrika_id(): void
    {
        $product = Product::query()->create(['name' => 'Аккумулятор', 'slug' => 'battery-test', 'price' => 5000, 'quantity' => 5, 'status' => true]);

        $this->postJson(route('quick-order.store', $product), ['name' => 'Тест', 'phone' => '89870000000', 'metrika_client_id' => '123456789'])
            ->assertOk();
        $this->assertSame('123456789', Order::query()->firstOrFail()->metrika_client_id);

        $this->post(route('cart.store', $product), ['quantity' => 1]);
        $this->post(route('checkout.store'), ['name' => 'Тест', 'phone' => '89870000000', 'agree' => 1, 'delivery' => 'pickup', 'payment' => 'cash', 'metrika_client_id' => '987654321'])
            ->assertRedirect(route('checkout.success'));
        $this->assertSame('987654321', Order::query()->latest('id')->firstOrFail()->metrika_client_id);
    }

    public function test_invalid_metrika_id_does_not_create_an_order(): void
    {
        $product = Product::query()->create(['name' => 'Аккумулятор', 'slug' => 'battery-test', 'price' => 5000, 'status' => true]);
        $this->postJson(route('quick-order.store', $product), ['name' => 'Тест', 'phone' => '89870000000', 'metrika_client_id' => 'not-an-id'])
            ->assertUnprocessable()->assertJsonValidationErrors('metrika_client_id');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_status_change_queues_sync_only_when_configured(): void
    {
        Queue::fake();
        config(['services.metrika.oauth_token' => 'test-token']);
        $order = Order::query()->create(['customer_name' => 'Тест', 'status' => OrderStatus::New, 'total' => 5000, 'metrika_client_id' => '12345']);
        $order->update(['status' => OrderStatus::Completed]);
        Queue::assertPushed(SyncOrderToMetrika::class, 2);

        config(['services.metrika.oauth_token' => null]);
        $order->update(['status' => OrderStatus::Cancelled]);
        Queue::assertPushed(SyncOrderToMetrika::class, 2);
    }

    public function test_sync_sends_current_status_and_non_sensitive_order_data(): void
    {
        Http::fake(['api-metrika.yandex.net/*' => Http::response(['uploading' => ['api_validation_status' => 'PASSED']], 200)]);
        config(['services.metrika.oauth_token' => 'test-token', 'shop.metrika_counter_id' => 12345]);
        $order = Order::query()->create(['customer_name' => 'Тест', 'customer_phone' => '89870000000', 'status' => OrderStatus::Completed, 'total' => 5000, 'metrika_client_id' => '123456789']);

        (new SyncOrderToMetrika($order->id))->handle();

        Http::assertSent(function ($request) use ($order): bool {
            $body = $request->body();

            return str_contains($request->url(), '/counter/12345/data/simple_orders?')
                && $request->hasHeader('Authorization', 'OAuth test-token')
                && str_contains($body, 'PAID')
                && str_contains($body, '123456789')
                && str_contains($body, (string) $order->id)
                && ! str_contains($body, '89870000000');
        });
    }

    public function test_sync_uses_latest_cancelled_status_and_skips_orders_without_client_id(): void
    {
        Http::fake(['api-metrika.yandex.net/*' => Http::response(['uploading' => ['api_validation_status' => 'PASSED']], 200)]);
        config(['services.metrika.oauth_token' => 'test-token', 'shop.metrika_counter_id' => 12345]);
        $order = Order::query()->create(['customer_name' => 'Тест', 'status' => OrderStatus::Completed, 'total' => 5000, 'metrika_client_id' => '12345']);
        $job = new SyncOrderToMetrika($order->id);
        $order->update(['status' => OrderStatus::Cancelled]);
        $job->handle();

        Http::assertSent(fn ($request): bool => str_contains($request->body(), 'CANCELLED'));
        $order->update(['metrika_client_id' => null]);
        $job->handle();
        Http::assertSentCount(1);
    }

    public function test_a_failed_upload_is_not_silently_accepted(): void
    {
        Http::fake(['api-metrika.yandex.net/*' => Http::response(['uploading' => ['api_validation_status' => 'FAILED']], 200)]);
        config(['services.metrika.oauth_token' => 'test-token', 'shop.metrika_counter_id' => 12345]);
        $order = Order::query()->create(['customer_name' => 'Тест', 'status' => OrderStatus::Completed, 'total' => 5000, 'metrika_client_id' => '12345']);

        $this->expectException(\RuntimeException::class);
        (new SyncOrderToMetrika($order->id))->handle();
    }
}
