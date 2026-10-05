<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\CustomerRequest;

use App\Models\CustomerRequest;
use App\MoonShine\Resources\CustomerRequest\Pages\CustomerRequestDetailPage;
use App\MoonShine\Resources\CustomerRequest\Pages\CustomerRequestFormPage;
use App\MoonShine\Resources\CustomerRequest\Pages\CustomerRequestIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;

/**
 * @extends ModelResource<CustomerRequest, CustomerRequestIndexPage, CustomerRequestFormPage, CustomerRequestDetailPage>
 */
class CustomerRequestResource extends ModelResource
{
    protected string $model = CustomerRequest::class;

    protected string $title = 'Заявки';

    protected string $column = 'name';

    /**
     * Заявки создаёт сайт.
     */
    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::CREATE);
    }

    /**
     * @return string[]
     */
    protected function search(): array
    {
        return ['id', 'name', 'phone', 'comment'];
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            CustomerRequestIndexPage::class,
            CustomerRequestFormPage::class,
            CustomerRequestDetailPage::class,
        ];
    }
}
