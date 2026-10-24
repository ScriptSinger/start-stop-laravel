<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\AccessLog;

use App\MoonShine\Resources\AccessLog\Pages\AccessLogDetailPage;
use App\MoonShine\Resources\AccessLog\Pages\AccessLogIndexPage;
use App\MoonShine\Support\LogResource;
use App\Support\AccessLogReader;
use App\Support\JsonLogReader;
use MoonShine\Support\Enums\Color;
use MoonShine\UI\Components\Badge;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Text;

/**
 * Read-only view over storage/logs/access-*.log (see AccessLogReader).
 */
class AccessLogResource extends LogResource
{
    protected string $title = 'Access Logs';

    protected function pages(): array
    {
        return [
            AccessLogIndexPage::class,
            AccessLogDetailPage::class,
        ];
    }

    protected function reader(): JsonLogReader
    {
        return app(AccessLogReader::class);
    }

    protected function indexFields(): iterable
    {
        return [
            Text::make('Время', 'time')->sortable('time_sort'),
            Text::make('Метод', 'method')->sortable(),
            Text::make('Путь', 'path')->sortable(),
            Preview::make('Статус', 'status', fn (array $row) => (string) Badge::make(
                (string) $row['status'],
                $this->statusColor($row['status']),
            ))->sortable(),
            Text::make('мс', 'duration_ms')->sortable(),
            Preview::make('Slow', 'is_slow', fn (array $row) => $this->flag($row['is_slow'], 'slow', Color::WARNING))->sortable(),
            Preview::make('Bot', 'is_bot', fn (array $row) => $this->flag($row['is_bot'], 'bot', Color::SECONDARY))->sortable(),
            Preview::make('Подозрит.', 'is_suspicious', fn (array $row) => $this->flag($row['is_suspicious'], 'suspicious', Color::ERROR))->sortable(),
            Text::make('IP', 'ip')->sortable(),
            Text::make('Referer', 'referer')->sortable(),
            Text::make('User-Agent', 'user_agent')->sortable(),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            Text::make('Время', 'time'),
            Text::make('Request ID', 'request_id'),
            Text::make('Файл', 'file'),
            Text::make('Метод', 'method'),
            Text::make('Путь', 'path'),
            Text::make('Query string', 'query'),
            Preview::make('Статус', 'status', fn (array $row) => (string) Badge::make(
                (string) $row['status'],
                $this->statusColor($row['status']),
            )),
            Text::make('Длительность, мс', 'duration_ms'),
            Text::make('Размер ответа', 'response_size'),
            Text::make('IP', 'ip'),
            Text::make('User-Agent', 'user_agent'),
            Text::make('Referer', 'referer'),
            Text::make('Admin user ID', 'admin_user_id'),
            Preview::make('Флаги', 'is_suspicious', fn (array $row) => $this->flags($row)),
            Preview::make('Запись', 'raw', fn (array $row) => '<pre class="whitespace-pre-wrap">'.e($row['raw']).'</pre>'),
        ];
    }

    private function statusColor(int $status): Color
    {
        return match (intdiv($status, 100)) {
            2 => Color::SUCCESS,
            3 => Color::INFO,
            4 => Color::WARNING,
            5 => Color::ERROR,
            default => Color::SECONDARY,
        };
    }

    private function flag(bool $on, string $label, Color $color): string
    {
        return $on ? (string) Badge::make($label, $color) : '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function flags(array $row): string
    {
        $badges = array_filter([
            $row['is_suspicious'] ? Badge::make('suspicious', Color::ERROR) : null,
            $row['is_slow'] ? Badge::make('slow', Color::WARNING) : null,
            $row['is_bot'] ? Badge::make('bot', Color::SECONDARY) : null,
        ]);

        return implode(' ', array_map('strval', $badges));
    }
}
