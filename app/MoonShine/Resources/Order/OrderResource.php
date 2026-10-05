<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Order;

use App\Models\Order;
use App\MoonShine\Resources\Order\Pages\OrderDetailPage;
use App\MoonShine\Resources\Order\Pages\OrderFormPage;
use App\MoonShine\Resources\Order\Pages\OrderIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;

/**
 * @extends ModelResource<Order, OrderIndexPage, OrderFormPage, OrderDetailPage>
 */
class OrderResource extends ModelResource
{
    protected string $model = Order::class;

    protected string $title = 'Заказы';

    protected string $column = 'customer_name';

    protected array $with = ['customer'];

    /**
     * Заказы создаёт сайт; в админке без позиций и пересчёта суммы
     * создавать заказ бессмысленно.
     */
    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::CREATE, Action::MASS_DELETE);
    }

    /**
     * @return string[]
     */
    protected function search(): array
    {
        return ['id', 'customer_name', 'customer_phone', 'customer_email'];
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            OrderIndexPage::class,
            OrderFormPage::class,
            OrderDetailPage::class,
        ];
    }
}
