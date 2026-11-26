<?php

namespace Tests\Feature;

use App\Actions\PlaceOrder;
use App\Jobs\SendOrderToTelegram;
use App\Models\Product;
use App\Services\Cart\CartLine;
use App\Services\Telegram\TelegramBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TelegramOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_is_queued_and_sent_once_with_order_details(): void
    {
        config(['services.telegram.bot_token' => 'test-token', 'services.telegram.chat_id' => '-1001234', 'services.telegram.topic_id' => '123', 'services.telegram.proxy' => null]);
        Queue::fake([SendOrderToTelegram::class]);
        Http::preventStrayRequests();
        Http::fake(['https://api.telegram.org/bottest-token/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 77]])]);
        $product = Product::query()->create(['name' => 'Аккумулятор', 'slug' => 'battery', 'price' => 5000]);

        $order = app(PlaceOrder::class)->handle(collect([new CartLine($product, 2, tradeIn: false)]), null, null, [
            'name' => 'Анна', 'phone' => '89870000000', 'email' => null, 'address' => null, 'comment' => 'Позвонить',
        ]);

        Queue::assertPushed(SendOrderToTelegram::class, fn ($job): bool => $job->orderId === $order->id && $job->afterCommit === true);
        $job = new SendOrderToTelegram($order->id);
        $job->handle(app(TelegramBot::class));
        $job->handle(app(TelegramBot::class));

        $this->assertSame(77, $order->fresh()->telegram_message_id);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request['chat_id'] === '-1001234' && $request['message_thread_id'] === 123
            && str_contains($request['text'], 'Заказ #'.$order->id)
            && str_contains($request['text'], 'Аккумулятор — 2 × 5 000,00 ₽')
            && str_contains($request['text'], 'Итого: 10 000,00 ₽')
            && str_contains($request['text'], 'Позвонить'));
    }

    public function test_order_does_not_queue_notification_when_bot_is_unconfigured(): void
    {
        config(['services.telegram.bot_token' => null]);
        Queue::fake([SendOrderToTelegram::class]);
        $product = Product::query()->create(['name' => 'Аккумулятор', 'slug' => 'battery', 'price' => 5000]);

        app(PlaceOrder::class)->handle(collect([new CartLine($product, 1, tradeIn: false)]), null, null, [
            'name' => 'Анна', 'phone' => '89870000000', 'email' => null, 'address' => null, 'comment' => null,
        ]);

        Queue::assertNotPushed(SendOrderToTelegram::class);
    }
}
