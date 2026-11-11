<?php

namespace App\Jobs;

use App\Services\Telegram\TelegramBot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeleteCustomerRequestTelegramMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public int $messageId)
    {
        $this->onConnection('database');
    }

    public function handle(TelegramBot $telegram): void
    {
        $telegram->deleteMessage($this->messageId);
    }
}
