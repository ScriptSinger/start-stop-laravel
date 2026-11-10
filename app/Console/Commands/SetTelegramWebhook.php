<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBot;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('telegram:set-webhook {url : Публичный HTTPS-адрес /api/telegram/webhook}')]
#[Description('Зарегистрировать webhook Telegram для кнопок заявок')]
class SetTelegramWebhook extends Command
{
    public function handle(TelegramBot $telegram): int
    {
        $url = $this->argument('url');

        if (! is_string($url) || ! str_starts_with($url, 'https://')
            || parse_url($url, PHP_URL_PATH) !== '/api/telegram/webhook'
            || parse_url($url, PHP_URL_QUERY) !== null
            || blank(config('services.telegram.bot_token')) || blank(config('services.telegram.chat_id'))
            || ! preg_match('/^-?[0-9]+$/D', (string) config('services.telegram.chat_id'))
            || ! preg_match('/^[A-Za-z0-9_-]{1,256}$/D', (string) config('services.telegram.webhook_secret'))) {
            $this->error('Укажите HTTPS-адрес /api/telegram/webhook и настройте токен, группу и секрет.');

            return self::FAILURE;
        }

        try {
            $telegram->registerWebhook($url);
        } catch (RuntimeException) {
            $this->error('Telegram не принял webhook. Проверьте адрес и настройки бота.');

            return self::FAILURE;
        }

        $this->info('Webhook Telegram зарегистрирован.');

        return self::SUCCESS;
    }
}
