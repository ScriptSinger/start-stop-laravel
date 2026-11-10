<?php

namespace App\Services\Telegram;

use App\Models\CustomerRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class TelegramBot
{
    public function sendRequest(CustomerRequest $customerRequest): int
    {
        $data = [
            'chat_id' => config('services.telegram.chat_id'),
            'text' => $this->message($customerRequest),
            'reply_markup' => $this->keyboard($customerRequest),
        ];

        $topicId = config('services.telegram.topic_id');

        if (filled($topicId)) {
            if (! preg_match('/^[1-9][0-9]*$/D', (string) $topicId) || filter_var($topicId, FILTER_VALIDATE_INT) === false) {
                throw new RuntimeException('Invalid Telegram topic ID.');
            }

            $data['message_thread_id'] = (int) $topicId;
        }

        $result = $this->call('sendMessage', $data);

        $messageId = $result['message_id'] ?? null;

        if (! is_int($messageId) || $messageId < 1) {
            throw new RuntimeException('Telegram did not return a message ID.');
        }

        return $messageId;
    }

    public function isGroupMember(int $userId): bool
    {
        $member = $this->call('getChatMember', [
            'chat_id' => config('services.telegram.chat_id'),
            'user_id' => $userId,
        ]);

        return in_array($member['status'] ?? null, ['creator', 'administrator', 'member'], true)
            || (($member['status'] ?? null) === 'restricted' && ($member['is_member'] ?? false) === true);
    }

    public function answerCallback(string $callbackId, string $message): void
    {
        $this->call('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => $message,
        ]);
    }

    public function editRequest(CustomerRequest $customerRequest): void
    {
        $this->call('editMessageText', [
            'chat_id' => config('services.telegram.chat_id'),
            'message_id' => $customerRequest->telegram_message_id,
            'text' => $this->message($customerRequest),
            'reply_markup' => $this->keyboard($customerRequest),
        ], allowNotModified: true);
    }

    public function registerWebhook(string $url): void
    {
        $this->call('setWebhook', [
            'url' => $url,
            'secret_token' => config('services.telegram.webhook_secret'),
            'allowed_updates' => ['callback_query'],
        ]);
    }

    private function message(CustomerRequest $customerRequest): string
    {
        $lines = [
            'Заявка #'.$customerRequest->id,
            'Тип: '.$customerRequest->type->toString(),
            'Статус: '.($customerRequest->is_processed ? 'Обработана' : 'Новая'),
            'Имя: '.$customerRequest->name,
        ];

        if ($customerRequest->phone) {
            $lines[] = 'Телефон: '.$customerRequest->phone;
        }

        if ($customerRequest->email) {
            $lines[] = 'Email: '.$customerRequest->email;
        }

        if ($customerRequest->product) {
            $lines[] = 'Товар: '.$customerRequest->product->name;
        }

        if ($customerRequest->comment) {
            $lines[] = 'Комментарий: '.Str::limit($customerRequest->comment, 1500);
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{inline_keyboard: list<list<array{text: string, callback_data: string}>>}
     */
    private function keyboard(CustomerRequest $customerRequest): array
    {
        return ['inline_keyboard' => [[
            $customerRequest->is_processed
                ? ['text' => '↩️ Вернуть в новые', 'callback_data' => 'request:'.$customerRequest->id.':reopen']
                : ['text' => '✅ Обработано', 'callback_data' => 'request:'.$customerRequest->id.':done'],
        ]]];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function call(string $method, array $data, bool $allowNotModified = false): array
    {
        $token = config('services.telegram.bot_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Telegram bot token is not configured.');
        }

        try {
            $response = Http::connectTimeout(3)->timeout(8)->asJson()
                ->withOptions(array_filter(['proxy' => config('services.telegram.proxy')]))
                ->post('https://api.telegram.org/bot'.$token.'/'.$method, $data);
        } catch (ConnectionException) {
            throw new RuntimeException('Telegram API connection failed.');
        }

        if ($allowNotModified && $response->status() === 400
            && str_contains((string) $response->json('description'), 'message is not modified')) {
            return [];
        }

        if (! $response->successful() || $response->json('ok') !== true) {
            $description = $response->json('description');
            $knownErrors = [
                'Bad Request: chat not found',
                'Bad Request: message thread not found',
                'Bad Request: topic_id_invalid',
                'Bad Request: TOPIC_CLOSED',
                'Forbidden: bot is not a member of the supergroup chat',
                'Forbidden: bot was kicked from the supergroup chat',
                'Forbidden: bot was blocked by the user',
                'Unauthorized',
            ];
            $detail = is_string($description) && in_array($description, $knownErrors, true) ? ' '.$description : '';

            throw new RuntimeException('Telegram API '.$method.' failed with HTTP '.$response->status().'.'.$detail);
        }

        $result = $response->json('result');

        return is_array($result) ? $result : [];
    }
}
