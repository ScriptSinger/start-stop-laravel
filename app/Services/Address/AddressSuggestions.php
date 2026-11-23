<?php

namespace App\Services\Address;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Подсказки адреса доставки (DaData). Запрос идёт с нашего сервера, чтобы ключ
 * не попадал в браузер. Подсказки только по Уфе. Любая ошибка DaData (нет ключа,
 * лимит, сеть) даёт пустой список: поле адреса остаётся обычным, заказ оформить можно.
 * Одинаковые запросы кэшируются на несколько минут, чтобы не тратить суточный лимит.
 */
class AddressSuggestions
{
    private const MAX_SUGGESTIONS = 7;

    private const CACHE_MINUTES = 10;

    public function isEnabled(): bool
    {
        return filled(config('services.dadata.key'));
    }

    /**
     * @return list<string>
     */
    public function suggest(string $query): array
    {
        $query = trim($query);

        if (! $this->isEnabled() || $query === '') {
            return [];
        }

        $key = 'address-suggestions:'.md5(mb_strtolower($query));

        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $suggestions = $this->fetch($query);

        if ($suggestions !== null) {
            Cache::put($key, $suggestions, now()->addMinutes(self::CACHE_MINUTES));
        }

        return $suggestions ?? [];
    }

    /**
     * @return list<string>|null null — DaData не ответила, такой результат не кэшируем
     */
    private function fetch(string $query): ?array
    {
        try {
            $response = Http::timeout(3)
                ->acceptJson()
                ->withHeaders(['Authorization' => 'Token '.config('services.dadata.key')])
                ->post(config('services.dadata.url'), [
                    'query' => $query,
                    'count' => self::MAX_SUGGESTIONS,
                    'locations' => [['kladr_id' => config('services.dadata.city_kladr_id')]],
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('DaData: нет ответа', ['error' => $exception->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('DaData: ошибка подсказок', ['status' => $response->status()]);

            return null;
        }

        return collect($response->json('suggestions', []))
            ->pluck('value')
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->values()
            ->all();
    }
}
