<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ServiceOffer\Pages;

use App\Enums\ServicePriceType;
use App\Models\ServiceOffer;
use App\MoonShine\Resources\ServiceOffer\ServiceOfferResource;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\Enums\Ability;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<ServiceOfferResource>
 */
class ServiceOfferIndexPage extends IndexPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        $canUpdate = fn (): bool => $this->getResource()->can(Ability::UPDATE);

        return [
            Text::make('Услуга', 'title')->sortable(),
            Enum::make('Тип цены', 'price_type')->attach(ServicePriceType::class)->sortable(),
            // Сумма меняется прямо в списке; у типов без суммы она не используется.
            Number::make('Сумма, ₽', 'price')
                ->min(0)
                ->nullable()
                ->updateOnPreview(condition: $canUpdate)
                ->sortable(),
            Preview::make('Как видит покупатель', 'id', fn (ServiceOffer $offer): string => e($offer->priceLabel())),
            Number::make('Порядок', 'sort_order')->sortable(),
            Switcher::make('Показывать на сайте', 'is_active')->updateOnPreview(condition: $canUpdate)->sortable(),
        ];
    }
}
