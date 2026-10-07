<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\OrderItem;

use App\Models\OrderItem;
use App\MoonShine\Resources\Concerns\ResetsPageOutOfRange;
use App\MoonShine\Resources\OrderItem\Pages\OrderItemDetailPage;
use App\MoonShine\Resources\OrderItem\Pages\OrderItemFormPage;
use App\MoonShine\Resources\OrderItem\Pages\OrderItemIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;

/**
 * @extends ModelResource<OrderItem, OrderItemIndexPage, OrderItemFormPage, OrderItemDetailPage>
 */
class OrderItemResource extends ModelResource
{
    use ResetsPageOutOfRange;

    protected string $model = OrderItem::class;

    protected string $title = 'Позиции заказа';

    protected string $column = 'name';

    /**
     * Позиция — снимок названия и цены на момент заказа: правка задним
     * числом разошлась бы с суммой заказа.
     */
    protected function activeActions(): ListOf
    {
        return new ListOf(Action::class, [Action::VIEW]);
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            OrderItemIndexPage::class,
            OrderItemFormPage::class,
            OrderItemDetailPage::class,
        ];
    }
}
