<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Telegram\TelegramBot;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOrderToTelegram implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public int $orderId)
    {
        $this->onConnection('database');
    }

    public function uniqueId(): string
    {
        return (string) $this->orderId;
    }

    public function failed(?\Throwable $exception): void
    {
        Order::query()->whereKey($this->orderId)->whereNull('telegram_message_id')
            ->update(['telegram_delivery_error' => 'Не удалось отправить после повторных попыток.']);
    }

    public function handle(TelegramBot $telegram): void
    {
        $order = Order::query()->with('items')->find($this->orderId);

        if (! $order || $order->telegram_message_id !== null
            || blank(config('services.telegram.bot_token')) || blank(config('services.telegram.chat_id'))) {
            return;
        }

        $messageId = $telegram->sendOrder($order);
        $order->forceFill(['telegram_message_id' => $messageId, 'telegram_delivery_error' => null])->save();
    }
}
