<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\AccessLog\Pages;

use App\MoonShine\Resources\AccessLog\AccessLogResource;
use App\Support\AccessLogReader;
use Illuminate\Support\Collection;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Crud\QueryTags\QueryTag;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<AccessLogResource>
 */
class AccessLogIndexPage extends IndexPage
{
    protected bool $isLazy = false;

    protected function filters(): iterable
    {
        $files = app(AccessLogReader::class)->fileNames();

        return [
            Select::make('Файл', 'file')
                ->options(array_combine($files, $files) ?: [])
                ->nullable(),
            Date::make('С даты', 'date_from'),
            Date::make('По дату', 'date_to'),
            Text::make('Путь содержит', 'path'),
            Text::make('IP содержит', 'ip'),
            Text::make('Исключить IP', 'exclude_ip')->hint('Точные адреса через запятую или пробел'),
            Switcher::make('Скрыть мой IP', 'hide_my_ip'),
            Text::make('User-Agent содержит', 'user_agent'),
            Switcher::make('Без ботов', 'no_bots'),
            Switcher::make('Только боты', 'only_bots'),
            Switcher::make('Только подозрительные', 'only_suspicious'),
            Switcher::make('Только медленные', 'only_slow'),
        ];
    }

    /**
     * @return list<QueryTag>
     */
    protected function queryTags(): array
    {
        $status = static fn (callable $test) => static fn (Collection $rows): Collection => $rows
            ->filter(static fn (array $row): bool => $test($row['status']))
            ->values();

        return [
            QueryTag::make('Все', static fn (Collection $rows): Collection => $rows)
                ->alias('all')
                ->default(),
            QueryTag::make('200', $status(static fn (int $s): bool => $s === 200))->alias('200'),
            QueryTag::make('3xx', $status(static fn (int $s): bool => intdiv($s, 100) === 3))->alias('3xx'),
            QueryTag::make('403', $status(static fn (int $s): bool => $s === 403))->alias('403'),
            QueryTag::make('404', $status(static fn (int $s): bool => $s === 404))->alias('404'),
            QueryTag::make('429', $status(static fn (int $s): bool => $s === 429))->alias('429'),
            QueryTag::make('4xx', $status(static fn (int $s): bool => intdiv($s, 100) === 4))->alias('4xx'),
            QueryTag::make('5xx', $status(static fn (int $s): bool => intdiv($s, 100) === 5))->alias('5xx'),
        ];
    }

    /**
     * Ширину колонок можно менять перетаскиванием, см. resources/js/admin/tables.js.
     * Проценты по порядку колонок, включая последнюю колонку с кнопками.
     *
     * @param  TableBuilder  $component
     */
    protected function modifyListComponent(ComponentContract $component): ComponentContract
    {
        return $component->customAttributes(['data-resizable' => '', 'data-widths' => '9,5,16,5,4,4,4,6,8,12,22,5']);
    }
}
