<?php

namespace App\Jobs;

use App\Models\CustomerRequest;
use App\Services\Telegram\TelegramBot;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCustomerRequestToTelegram implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public int $customerRequestId)
    {
        $this->onConnection('database');
    }

    public function uniqueId(): string
    {
        return (string) $this->customerRequestId;
    }

    public function handle(TelegramBot $telegram): void
    {
        $customerRequest = CustomerRequest::query()->with('product')->find($this->customerRequestId);

        if (! $customerRequest || $customerRequest->telegram_message_id !== null
            || blank(config('services.telegram.bot_token')) || blank(config('services.telegram.chat_id'))) {
            return;
        }

        $messageId = $telegram->sendRequest($customerRequest);
        $customerRequest->forceFill(['telegram_message_id' => $messageId])->save();
    }
}
