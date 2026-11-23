<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ServiceOffer;

use App\Models\ServiceOffer;
use App\MoonShine\Resources\ServiceOffer\Pages\ServiceOfferFormPage;
use App\MoonShine\Resources\ServiceOffer\Pages\ServiceOfferIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\Support\ListOf;

/**
 * Карточки страницы «Услуги»: тип цены, сумма (для «фикс» и «от») и условия.
 *
 * @extends ModelResource<ServiceOffer, ServiceOfferIndexPage, ServiceOfferFormPage, null>
 */
class ServiceOfferResource extends ModelResource
{
    protected string $model = ServiceOffer::class;

    protected string $title = 'Услуги';

    protected string $column = 'title';

    protected string $sortColumn = 'sort_order';

    protected SortDirection $sortDirection = SortDirection::ASC;

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::VIEW);
    }

    /**
     * @return string[]
     */
    protected function search(): array
    {
        return ['id', 'title', 'slug'];
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            ServiceOfferIndexPage::class,
            ServiceOfferFormPage::class,
        ];
    }
}
