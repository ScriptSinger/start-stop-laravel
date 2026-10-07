<?php

declare(strict_types=1);

namespace App\Providers;

use App\MoonShine\Pages\StockReport;
use App\MoonShine\Resources\Attribute\AttributeResource;
use App\MoonShine\Resources\AttributeValue\AttributeValueResource;
use App\MoonShine\Resources\Banner\BannerResource;
use App\MoonShine\Resources\BatteryFitment\BatteryFitmentResource;
use App\MoonShine\Resources\Category\CategoryResource;
use App\MoonShine\Resources\Customer\CustomerResource;
use App\MoonShine\Resources\CustomerRequest\CustomerRequestResource;
use App\MoonShine\Resources\Manufacturer\ManufacturerResource;
use App\MoonShine\Resources\MenuItem\MenuItemResource;
use App\MoonShine\Resources\MoonShineUser\MoonShineUserResource;
use App\MoonShine\Resources\MoonShineUserRole\MoonShineUserRoleResource;
use App\MoonShine\Resources\Order\OrderResource;
use App\MoonShine\Resources\OrderItem\OrderItemResource;
use App\MoonShine\Resources\Page\PageResource;
use App\MoonShine\Resources\Product\ProductResource;
use App\MoonShine\Resources\ProductImage\ProductImageResource;
use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\DependencyInjection\MoonShineConfigurator;

class MoonShineServiceProvider extends ServiceProvider
{
    /**
     * @param  CoreContract<MoonShineConfigurator>  $core
     */
    public function boot(CoreContract $core): void
    {
        $core
            ->resources([
                MoonShineUserResource::class,
                MoonShineUserRoleResource::class,
                ProductResource::class,
                CategoryResource::class,
                BatteryFitmentResource::class,
                ManufacturerResource::class,
                AttributeResource::class,
                AttributeValueResource::class,
                OrderResource::class,
                OrderItemResource::class,
                CustomerResource::class,
                PageResource::class,
                ProductImageResource::class,
                MenuItemResource::class,
                CustomerRequestResource::class,
                BannerResource::class,
            ])
            ->pages([
                ...$core->getConfig()->getPages(),
                StockReport::class,
            ]);
    }
}
