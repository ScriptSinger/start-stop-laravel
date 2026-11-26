<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Telegram\TelegramBot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class UpdateOrderTelegramMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public int $orderId)
    {
        $this->onConnection('database');
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->orderId))->releaseAfter(5)->expireAfter(60)];
    }

    public function handle(TelegramBot $telegram): void
    {
        $order = Order::query()->with('items')->find($this->orderId);

        if (! $order || $order->telegram_message_id === null
            || blank(config('services.telegram.bot_token')) || blank(config('services.telegram.chat_id'))) {
            return;
        }

        $telegram->editOrder($order);
    }
}
