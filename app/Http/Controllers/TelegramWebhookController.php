<?php

namespace App\Http\Controllers;

use App\Models\CustomerRequest;
use App\Services\Telegram\TelegramBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramBot $telegram): JsonResponse
    {
        $secret = config('services.telegram.webhook_secret');

        abort_unless(is_string($secret) && $secret !== '' && filled(config('services.telegram.chat_id')), 404);
        abort_unless(hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '')), 403);

        $callback = $request->input('callback_query');

        if (! is_array($callback)) {
            return response()->json(['ok' => true]);
        }

        $callbackId = $callback['id'] ?? null;
        $data = $callback['data'] ?? null;
        $chat = $callback['message']['chat'] ?? null;
        $messageId = $callback['message']['message_id'] ?? null;
        $userId = $callback['from']['id'] ?? null;

        if (! is_string($callbackId) || $callbackId === '' || mb_strlen($callbackId) > 255
            || ! is_string($data) || strlen($data) > 64
            || ! preg_match('/^request:([1-9][0-9]*):(done|reopen)$/D', $data, $matches)
            || ! is_array($chat) || ! in_array($chat['type'] ?? null, ['group', 'supergroup'], true)
            || (string) ($chat['id'] ?? '') !== (string) config('services.telegram.chat_id')
            || ! is_int($messageId) || $messageId < 1 || ! is_int($userId) || $userId < 1
            || ($callback['from']['is_bot'] ?? false) === true) {
            return response()->json(['ok' => true]);
        }

        $customerRequest = CustomerRequest::query()->with('product')->find((int) $matches[1]);

        if (! $customerRequest || $customerRequest->telegram_message_id !== $messageId) {
            $this->answer($telegram, $callbackId, 'Заявка не найдена.');

            return response()->json(['ok' => true]);
        }

        if (! $telegram->isGroupMember($userId)) {
            $this->answer($telegram, $callbackId, 'Только участник группы может обработать заявку.');

            return response()->json(['ok' => true]);
        }

        $processed = $matches[2] === 'done';
        $changed = CustomerRequest::query()->whereKey($customerRequest->id)
            ->where('telegram_message_id', $messageId)
            ->where('is_processed', ! $processed)
            ->update(['is_processed' => $processed]);

        $this->answer($telegram, $callbackId, $changed ? 'Статус заявки обновлён.' : 'Статус уже изменён.');
        $telegram->editRequest($customerRequest->fresh('product'));

        return response()->json(['ok' => true]);
    }

    private function answer(TelegramBot $telegram, string $callbackId, string $message): void
    {
        try {
            $telegram->answerCallback($callbackId, $message);
        } catch (RuntimeException) {
            // Telegram повторит webhook, если последующее обновление сообщения не удалось.
        }
    }
}
