<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use App\Enums\OrderStatus;
use App\Models\CustomerRequest;
use App\Models\Order;
use App\MoonShine\Pages\Dashboard;
use App\MoonShine\Pages\StockReport;
use App\MoonShine\Resources\Attribute\AttributeResource;
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
use App\MoonShine\Resources\Page\PageResource;
use App\MoonShine\Resources\Product\ProductResource;
use MoonShine\ColorManager\ColorManager;
use MoonShine\ColorManager\Palettes\PurplePalette;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Contracts\ColorManager\PaletteContract;
use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\MenuManager\MenuGroup;
use MoonShine\MenuManager\MenuItem;

final class MoonShineLayout extends AppLayout
{
    /**
     * @var null|class-string<PaletteContract>
     */
    protected ?string $palette = PurplePalette::class;

    protected function assets(): array
    {
        return [
            ...parent::assets(),
        ];
    }

    /**
     * Сверху — ежедневная работа (заказы, заявки), ниже каталог и наполнение
     * сайта, внизу системные разделы MoonShine. Иконки — у всех пунктов,
     * чтобы группы и одиночные пункты выглядели одинаково.
     */
    protected function menu(): array
    {
        $newOrders = Order::query()->where('status', OrderStatus::New)->count();
        $newRequests = CustomerRequest::query()->where('is_processed', false)->count();

        return [
            MenuItem::make(Dashboard::class, 'Главная')->icon('home'),
            // Счётчик новых — видно сразу, без захода на панель. Ставим только
            // ненулевой: пустой badge MoonShine передаёт кнопке как false и падает.
            MenuItem::make(OrderResource::class, 'Заказы')->icon('shopping-cart')
                ->when($newOrders > 0, fn (MenuItem $item): MenuItem => $item->badge($newOrders)),
            MenuItem::make(CustomerRequestResource::class, 'Заявки')->icon('chat-bubble-left-right')
                ->when($newRequests > 0, fn (MenuItem $item): MenuItem => $item->badge($newRequests)),
            MenuItem::make(CustomerResource::class, 'Клиенты')->icon('users'),
            MenuGroup::make('Каталог', [
                MenuItem::make(ProductResource::class, 'Товары')->icon('cube'),
                MenuItem::make(StockReport::class, 'Остатки')->icon('chart-bar'),
                MenuItem::make(CategoryResource::class, 'Категории')->icon('rectangle-stack'),
                MenuItem::make(ManufacturerResource::class, 'Производители')->icon('building-storefront'),
                MenuItem::make(AttributeResource::class, 'Характеристики')->icon('adjustments-horizontal'),
                MenuItem::make(BatteryFitmentResource::class, 'Подбор АКБ')->icon('battery-50'),
            ])->icon('squares-2x2'),
            MenuGroup::make('Сайт', [
                MenuItem::make(PageResource::class, 'Страницы')->icon('document-text'),
                MenuItem::make(MenuItemResource::class, 'Меню сайта')->icon('bars-3'),
                MenuItem::make(BannerResource::class, 'Баннеры')->icon('photo'),
            ])->icon('globe-alt'),
            MenuGroup::make('Система', [
                MenuItem::make(MoonShineUserResource::class, 'Администраторы')->icon('user-group'),
                MenuItem::make(MoonShineUserRoleResource::class, 'Роли')->icon('key'),
            ])->icon('cog-6-tooth'),
        ];
    }

    /**
     * @param  ColorManager  $colorManager
     */
    protected function colors(ColorManagerContract $colorManager): void
    {
        parent::colors($colorManager);

        // $colorManager->primary('#00000');
    }
}
