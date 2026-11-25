<?php

namespace App\MoonShine\Resources\StoreReview;

use App\Models\StoreReview;
use App\MoonShine\Resources\StoreReview\Pages\StoreReviewFormPage;
use App\MoonShine\Resources\StoreReview\Pages\StoreReviewIndexPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\Support\ListOf;

/** @extends ModelResource<StoreReview, StoreReviewIndexPage, StoreReviewFormPage, null> */
class StoreReviewResource extends ModelResource
{
    protected string $model = StoreReview::class;

    protected string $title = 'Отзывы о магазине';

    protected string $column = 'author';

    protected string $sortColumn = 'sort_order';

    protected SortDirection $sortDirection = SortDirection::ASC;

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::VIEW);
    }

    protected function search(): array
    {
        return ['id', 'author', 'body'];
    }

    protected function pages(): array
    {
        return [StoreReviewIndexPage::class, StoreReviewFormPage::class];
    }
}
