<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * Reads the JSON lines written by AccessLogMiddleware (channel `access`).
 */
class AccessLogReader extends JsonLogReader
{
    protected function prefix(): string
    {
        return 'access';
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
            'request_id' => (string) Arr::get($context, 'request_id'),
            'method' => (string) Arr::get($context, 'method'),
            'path' => (string) Arr::get($context, 'path'),
            'query' => (string) Arr::get($context, 'query'),
            'status' => (int) Arr::get($context, 'status'),
            'duration_ms' => (int) Arr::get($context, 'duration_ms'),
            'response_size' => Arr::get($context, 'response_size'),
            'ip' => (string) Arr::get($context, 'ip'),
            'user_agent' => (string) Arr::get($context, 'user_agent'),
            'referer' => (string) Arr::get($context, 'referer'),
            'admin_user_id' => Arr::get($context, 'admin_user_id'),
            'is_slow' => (bool) Arr::get($context, 'slow_request'),
            'is_bot' => (bool) Arr::get($context, 'is_bot'),
            'is_suspicious' => (bool) Arr::get($context, 'is_suspicious'),
        ];
    }

    protected function matchesOwn(array $row, array $filters): bool
    {
        if (! $this->contains($row, 'path', $filters) || ! $this->contains($row, 'user_agent', $filters)) {
            return false;
        }

        if ($this->truthy($filters['no_bots'] ?? false) && $row['is_bot']) {
            return false;
        }

        foreach (['only_bots' => 'is_bot', 'only_suspicious' => 'is_suspicious', 'only_slow' => 'is_slow'] as $filter => $flag) {
            if ($this->truthy($filters[$filter] ?? false) && ! $row[$flag]) {
                return false;
            }
        }

        return true;
    }
}
