<?php

namespace Tests\Feature;

use App\Actions\PlaceOrder;
use App\Enums\OrderStatus;
use App\Jobs\SendOrderToTelegram;
use App\Jobs\UpdateOrderTelegramMessage;
use App\Models\Order;
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
            && str_contains($request['text'], 'Позвонить')
            && $request['reply_markup']['inline_keyboard'][0][0]['callback_data'] === 'order:'.$order->id.':New:Pending');
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

    public function test_group_member_can_change_order_status_and_stale_button_cannot_overwrite_it(): void
    {
        config(['services.telegram.bot_token' => 'test-token', 'services.telegram.chat_id' => '-1001234', 'services.telegram.webhook_secret' => 'secret', 'services.telegram.proxy' => null]);
        Http::preventStrayRequests();
        Http::fake([
            'https://api.telegram.org/bottest-token/getChatMember' => Http::response(['ok' => true, 'result' => ['status' => 'member']]),
            'https://api.telegram.org/bottest-token/answerCallbackQuery' => Http::response(['ok' => true, 'result' => true]),
            'https://api.telegram.org/bottest-token/editMessageText' => Http::response(['ok' => true, 'result' => []]),
        ]);
        $order = Order::query()->create(['customer_name' => 'Анна', 'customer_phone' => '123', 'status' => OrderStatus::New, 'total' => 100]);
        $order->forceFill(['telegram_message_id' => 77])->save();
        $payload = ['callback_query' => ['id' => 'cb', 'data' => 'order:'.$order->id.':New:Processing', 'from' => ['id' => 123], 'message' => ['message_id' => 77, 'chat' => ['id' => -1001234, 'type' => 'supergroup']]]];

        $this->postJson(route('telegram.webhook'), $payload, ['X-Telegram-Bot-Api-Secret-Token' => 'secret'])->assertOk();
        $this->assertSame(OrderStatus::Processing, $order->fresh()->status);
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/editMessageText') && str_contains($request['text'], 'Статус: В обработке'));

        $payload['callback_query']['data'] = 'order:'.$order->id.':New:Cancelled';
        $this->postJson(route('telegram.webhook'), $payload, ['X-Telegram-Bot-Api-Secret-Token' => 'secret'])->assertOk();
        $this->assertSame(OrderStatus::Processing, $order->fresh()->status);
    }

    public function test_order_status_changes_in_admin_queue_message_update(): void
    {
        Queue::fake([UpdateOrderTelegramMessage::class]);
        $order = Order::query()->create(['customer_name' => 'Анна', 'customer_phone' => '123', 'status' => OrderStatus::New, 'total' => 100]);
        $order->forceFill(['telegram_message_id' => 77])->save();
        $order->update(['status' => OrderStatus::Completed]);

        Queue::assertPushed(UpdateOrderTelegramMessage::class, fn ($job): bool => $job->orderId === $order->id && $job->afterCommit === true);
    }

    public function test_update_job_sends_latest_order_status(): void
    {
        config(['services.telegram.bot_token' => 'test-token', 'services.telegram.chat_id' => '-1001234', 'services.telegram.proxy' => null]);
        Http::preventStrayRequests();
        Http::fake(['https://api.telegram.org/bottest-token/editMessageText' => Http::response(['ok' => true, 'result' => []])]);
        $order = Order::query()->create(['customer_name' => 'Анна', 'customer_phone' => '123', 'status' => OrderStatus::Completed, 'total' => 100]);
        $order->forceFill(['telegram_message_id' => 77])->save();

        (new UpdateOrderTelegramMessage($order->id))->handle(app(TelegramBot::class));

        Http::assertSent(fn ($request): bool => $request['message_id'] === 77 && str_contains($request['text'], 'Статус: Сделка завершена'));
    }

    public function test_non_member_cannot_change_order_status(): void
    {
        config(['services.telegram.bot_token' => 'test-token', 'services.telegram.chat_id' => '-1001234', 'services.telegram.webhook_secret' => 'secret', 'services.telegram.proxy' => null]);
        Http::preventStrayRequests();
        Http::fake([
            'https://api.telegram.org/bottest-token/getChatMember' => Http::response(['ok' => true, 'result' => ['status' => 'left']]),
            'https://api.telegram.org/bottest-token/answerCallbackQuery' => Http::response(['ok' => true, 'result' => true]),
        ]);
        $order = Order::query()->create(['customer_name' => 'Анна', 'customer_phone' => '123', 'status' => OrderStatus::New, 'total' => 100]);
        $order->forceFill(['telegram_message_id' => 77])->save();
        $payload = ['callback_query' => ['id' => 'cb', 'data' => 'order:'.$order->id.':New:Cancelled', 'from' => ['id' => 123], 'message' => ['message_id' => 77, 'chat' => ['id' => -1001234, 'type' => 'supergroup']]]];

        $this->postJson(route('telegram.webhook'), $payload, ['X-Telegram-Bot-Api-Secret-Token' => 'secret'])->assertOk();
        $this->assertSame(OrderStatus::New, $order->fresh()->status);
    }
}
