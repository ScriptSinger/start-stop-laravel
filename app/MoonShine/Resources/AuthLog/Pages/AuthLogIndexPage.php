<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\AuthLog\Pages;

use App\MoonShine\Resources\AuthLog\AuthLogResource;
use App\Support\AuthLogReader;
use Illuminate\Support\Collection;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Crud\QueryTags\QueryTag;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<AuthLogResource>
 */
class AuthLogIndexPage extends IndexPage
{
    protected bool $isLazy = false;

    protected function filters(): iterable
    {
        return [
            Date::make('С даты', 'date_from'),
            Date::make('По дату', 'date_to'),
            Text::make('Логин содержит', 'login'),
            Text::make('IP содержит', 'ip'),
            Text::make('Исключить IP', 'exclude_ip')->hint('Точные адреса через запятую или пробел'),
            Switcher::make('Скрыть мой IP', 'hide_my_ip'),
            Text::make('User-Agent содержит', 'user_agent'),
        ];
    }

    /**
     * @return list<QueryTag>
     */
    protected function queryTags(): array
    {
        $event = static fn (string $event) => static fn (Collection $rows): Collection => $rows
            ->where('event', $event)
            ->values();

        return [
            QueryTag::make('Все', static fn (Collection $rows): Collection => $rows)
                ->alias('all')
                ->default(),
            QueryTag::make('Неудачные', $event(AuthLogReader::EVENT_FAILED))->alias('failed'),
            QueryTag::make('Входы', $event(AuthLogReader::EVENT_LOGIN))->alias('login'),
            QueryTag::make('Выходы', $event(AuthLogReader::EVENT_LOGOUT))->alias('logout'),
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
        return $component->customAttributes(['data-resizable' => '', 'data-widths' => '15,15,22,13,30,5']);
    }
}
