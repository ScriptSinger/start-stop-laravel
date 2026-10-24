<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\AuthLog;

use App\MoonShine\Resources\AuthLog\Pages\AuthLogDetailPage;
use App\MoonShine\Resources\AuthLog\Pages\AuthLogIndexPage;
use App\MoonShine\Support\LogResource;
use App\Support\AuthLogReader;
use App\Support\JsonLogReader;
use MoonShine\Support\Enums\Color;
use MoonShine\UI\Components\Badge;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Text;

/**
 * Read-only view over storage/logs/auth-*.log: admin sign-ins, failed
 * attempts and sign-outs.
 */
class AuthLogResource extends LogResource
{
    protected string $title = 'Auth Logs';

    protected function pages(): array
    {
        return [
            AuthLogIndexPage::class,
            AuthLogDetailPage::class,
        ];
    }

    protected function reader(): JsonLogReader
    {
        return app(AuthLogReader::class);
    }

    protected function indexFields(): iterable
    {
        return [
            Text::make('Время', 'time')->sortable('time_sort'),
            Preview::make('Событие', 'event', fn (array $row) => $this->eventBadge($row['event']))->sortable(),
            Text::make('Логин', 'login')->sortable(),
            Text::make('IP', 'ip')->sortable(),
            Text::make('User-Agent', 'user_agent')->sortable(),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            Text::make('Время', 'time'),
            Preview::make('Событие', 'event', fn (array $row) => $this->eventBadge($row['event'])),
            Text::make('Логин', 'login'),
            Text::make('ID пользователя', 'user_id'),
            Preview::make('Такой пользователь есть', 'user_exists', static fn (array $row): string => match ($row['user_exists']) {
                true => 'да',
                false => 'нет',
                default => '—',
            }),
            Text::make('IP', 'ip'),
            Text::make('User-Agent', 'user_agent'),
            Text::make('Request ID', 'request_id'),
            Text::make('Файл', 'file'),
            Preview::make('Запись', 'raw', fn (array $row) => '<pre class="whitespace-pre-wrap">'.e($row['raw']).'</pre>'),
        ];
    }

    private function eventBadge(string $event): string
    {
        return (string) match ($event) {
            AuthLogReader::EVENT_FAILED => Badge::make('неудачный вход', Color::ERROR),
            AuthLogReader::EVENT_LOGIN => Badge::make('вход', Color::SUCCESS),
            AuthLogReader::EVENT_LOGOUT => Badge::make('выход', Color::SECONDARY),
            default => Badge::make($event, Color::GRAY),
        };
    }
}
