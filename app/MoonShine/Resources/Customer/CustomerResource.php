<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Customer;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\MoonShine\Resources\Concerns\ResetsPageOutOfRange;
use App\MoonShine\Resources\Customer\Pages\CustomerDetailPage;
use App\MoonShine\Resources\Customer\Pages\CustomerFormPage;
use App\MoonShine\Resources\Customer\Pages\CustomerIndexPage;
use Illuminate\Contracts\Database\Eloquent\Builder;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;

/**
 * @extends ModelResource<Customer, CustomerIndexPage, CustomerFormPage, CustomerDetailPage>
 */
class CustomerResource extends ModelResource
{
    use ResetsPageOutOfRange;

    protected string $model = Customer::class;

    protected string $title = 'Клиенты';

    protected string $column = 'name';

    /**
     * @return string[]
     */
    protected function search(): array
    {
        return ['id', 'name', 'email', 'phone'];
    }

    /**
     * «Купил на» — сумма состоявшихся заказов: брошенные и отменённые не считаем.
     */
    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        $placed = fn (Builder $orders): Builder => $orders->whereNotIn('status', [OrderStatus::Abandoned, OrderStatus::Cancelled]);

        return $builder
            ->withCount('orders')
            ->withSum(['orders' => $placed], 'total')
            ->withMax('orders', 'created_at');
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            CustomerIndexPage::class,
            CustomerFormPage::class,
            CustomerDetailPage::class,
        ];
    }
}
