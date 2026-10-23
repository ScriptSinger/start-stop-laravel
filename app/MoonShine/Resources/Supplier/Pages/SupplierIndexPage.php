<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Supplier\Pages;

use App\MoonShine\Resources\Supplier\SupplierResource;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<SupplierResource>
 */
class SupplierIndexPage extends IndexPage
{
    protected bool $isLazy = true;

    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Название', 'name')->sortable(),
            Text::make('Формат прайса', 'format', fn ($supplier): string => $supplier->format->label()),
            Number::make('Наценка, %', 'markup_percent')->sortable(),
            Number::make('Срок, раб. дней', 'lead_working_days')->sortable(),
            Text::make('Порог остатка', 'min_quantity', fn ($supplier): string => $supplier->ignore_quantity ? 'не учитывается' : 'от '.$supplier->min_quantity.' шт.'),
            Number::make('Позиций в прайсе', 'offers_count')->sortable(),
            Switcher::make('Активен', 'is_active'),
        ];
    }
}
