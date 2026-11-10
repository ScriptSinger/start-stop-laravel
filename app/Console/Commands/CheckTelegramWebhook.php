<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBot;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('telegram:check-webhook {url : Ожидаемый HTTPS-адрес webhook}')]
#[Description('Проверить production-бота и зарегистрированный webhook Telegram')]
class CheckTelegramWebhook extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TelegramBot $telegram): int
    {
        $expectedUrl = $this->argument('url');

        if (! is_string($expectedUrl) || ! str_starts_with($expectedUrl, 'https://')) {
            $this->error('Укажите ожидаемый HTTPS-адрес webhook.');

            return self::FAILURE;
        }

        try {
            if (strcasecmp($telegram->username(), (string) config('services.telegram.production_bot_username')) !== 0) {
                $this->error('Настроен не production-бот Telegram.');

                return self::FAILURE;
            }

            if ($telegram->webhookUrl() !== $expectedUrl) {
                $this->error('Зарегистрированный webhook не совпадает с ожидаемым.');

                return self::FAILURE;
            }
        } catch (RuntimeException) {
            $this->error('Не удалось проверить webhook Telegram.');

            return self::FAILURE;
        }

        $this->info('Production webhook Telegram настроен верно.');

        return self::SUCCESS;
    }
}
