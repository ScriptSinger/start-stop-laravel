<?php

namespace App\Jobs;

use App\Models\CustomerRequest;
use App\Services\Telegram\TelegramBot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class UpdateCustomerRequestTelegramMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public int $customerRequestId)
    {
        $this->onConnection('database');
    }

    /**
     * @return list<WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->customerRequestId))->releaseAfter(5)->expireAfter(60)];
    }

    public function handle(TelegramBot $telegram): void
    {
        $customerRequest = CustomerRequest::query()->with('product')->find($this->customerRequestId);

        if (! $customerRequest || $customerRequest->telegram_message_id === null
            || blank(config('services.telegram.bot_token')) || blank(config('services.telegram.chat_id'))) {
            return;
        }

        $telegram->editRequest($customerRequest);
    }
}
