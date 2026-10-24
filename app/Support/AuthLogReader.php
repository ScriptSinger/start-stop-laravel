<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * Reads the `auth` channel (storage/logs/auth-*.log), see
 * App\Listeners\LogAdminAuthEvent: events are login, logout and login_failed.
 */
class AuthLogReader extends JsonLogReader
{
    public const EVENT_LOGIN = 'login';

    public const EVENT_LOGOUT = 'logout';

    public const EVENT_FAILED = 'login_failed';

    /**
     * @param  int  $since  unix timestamp
     */
    public function failedLoginsSince(int $since): int
    {
        return $this->entries(['since' => $since])
            ->where('event', self::EVENT_FAILED)
            ->count();
    }

    protected function prefix(): string
    {
        return 'auth';
    }

    protected function row(string $file, int $line, array $decoded): array
    {
        $context = Arr::get($decoded, 'context');
        $context = is_array($context) ? $context : [];

        $time = $this->parseTime(Arr::get($decoded, 'datetime'));

        return [
            'id' => $file.':'.$line,
            'file' => $file,
            'time' => $time?->format('d.m.Y H:i:s'),
            'time_sort' => $time?->getTimestamp() ?? 0,
            'event' => (string) Arr::get($decoded, 'message'),
            'login' => (string) Arr::get($context, 'login'),
            'user_id' => Arr::get($context, 'user_id'),
            'user_exists' => Arr::get($context, 'user_exists'),
            'request_id' => (string) Arr::get($context, 'request_id'),
            'ip' => (string) Arr::get($context, 'ip'),
            'user_agent' => (string) Arr::get($context, 'user_agent'),
        ];
    }

    protected function matchesOwn(array $row, array $filters): bool
    {
        return $this->contains($row, 'login', $filters)
            && $this->contains($row, 'user_agent', $filters);
    }
}
