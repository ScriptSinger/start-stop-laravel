<?php

namespace App\MoonShine\Resources\StoreReview\Pages;

use App\Enums\StoreReviewSource;
use App\MoonShine\Resources\StoreReview\StoreReviewResource;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\Enums\Ability;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/** @extends IndexPage<StoreReviewResource> */
class StoreReviewIndexPage extends IndexPage
{
    protected function fields(): iterable
    {
        return [
            Text::make('Автор', 'author')->sortable(),
            Enum::make('Источник', 'source')->attach(StoreReviewSource::class)->sortable(),
            Number::make('Оценка', 'rating')->sortable(),
            Date::make('Дата отзыва', 'reviewed_at')->format('d.m.Y')->sortable(),
            Number::make('Порядок', 'sort_order')->sortable(),
            Switcher::make('Показывать на сайте', 'is_active')->updateOnPreview(condition: fn (): bool => $this->getResource()->can(Ability::UPDATE))->sortable(),
        ];
    }
}
