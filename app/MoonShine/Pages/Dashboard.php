<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Enums\CustomerRequestType;
use App\Models\CustomerRequest;
use App\Models\Order;
use App\Models\Product;
use App\MoonShine\Fields\Money;
use App\MoonShine\Resources\CustomerRequest\CustomerRequestResource;
use App\MoonShine\Resources\CustomerRequest\Pages\CustomerRequestFormPage;
use App\MoonShine\Resources\CustomerRequest\Pages\CustomerRequestIndexPage;
use App\MoonShine\Resources\Order\OrderResource;
use App\MoonShine\Resources\Order\Pages\OrderDetailPage;
use App\MoonShine\Resources\Order\Pages\OrderIndexPage;
use App\MoonShine\Resources\Product\Pages\ProductIndexPage;
use App\MoonShine\Resources\Product\ProductResource;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\Laravel\TypeCasts\ModelCaster;
use MoonShine\MenuManager\Attributes\SkipMenu;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Metrics\Wrapped\ValueMetric;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Phone;
use MoonShine\UI\Fields\Text;

/**
 * Стартовая страница админки. Уведомлений о заказах нет (так решили),
 * поэтому новые заказы должны быть видны сразу после входа.
 */
#[SkipMenu]
class Dashboard extends Page
{
    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            '#' => $this->getTitle(),
        ];
    }

    public function getTitle(): string
    {
        return $this->title ?: 'Панель';
    }

    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        $newRequests = CustomerRequest::query()
            ->where('is_processed', false)
            ->latest()
            ->orderByDesc('id')
            ->get();

        $newOrders = Order::query()
            ->where('status', 'new')
            ->latest()
            ->orderByDesc('id')
            ->get();

        return [
            Grid::make([
                Column::make([
                    ValueMetric::make('Новые заказы')->value($newOrders->count()),
                ], colSpan: 3, adaptiveColSpan: 12),
                Column::make([
                    ValueMetric::make('Новые заявки')->value($newRequests->count()),
                ], colSpan: 3, adaptiveColSpan: 12),
                Column::make([
                    ValueMetric::make('Заказов за 7 дней')
                        ->value(Order::query()->where('status', '!=', 'unknown')->where('created_at', '>=', now()->subDays(7))->count()),
                ], colSpan: 3, adaptiveColSpan: 12),
                Column::make([
                    ValueMetric::make('Активных товаров')->value(Product::query()->where('status', true)->count()),
                ], colSpan: 3, adaptiveColSpan: 12),
            ]),

            Box::make('Новые заказы — ждут звонка', [
                TableBuilder::make(items: $newOrders)
                    ->cast(new ModelCaster(Order::class))
                    ->fields([
                        ID::make(),
                        Date::make('Дата', 'created_at')->format('d.m.Y H:i'),
                        Text::make('Клиент', 'customer_name'),
                        Phone::make('Телефон', 'customer_phone'),
                        Text::make('Получение', 'delivery_method'),
                        Money::make('Сумма', 'total'),
                    ])
                    ->buttons([
                        ActionButton::make('Открыть', fn (Order $order): string => $this->pageUrl(OrderDetailPage::class, OrderResource::class, ['resourceItem' => $order->getKey()])),
                    ])
                    ->withNotFound(),
                ActionButton::make('Все заказы', $this->pageUrl(OrderIndexPage::class, OrderResource::class)),
            ]),

            Box::make('Новые заявки — перезвонить', [
                TableBuilder::make(items: $newRequests)
                    ->cast(new ModelCaster(CustomerRequest::class))
                    ->fields([
                        Date::make('Дата', 'created_at')->format('d.m.Y H:i'),
                        Enum::make('Тип', 'type')->attach(CustomerRequestType::class),
                        Text::make('Имя', 'name'),
                        Phone::make('Телефон', 'phone'),
                        Text::make('Комментарий', 'comment'),
                    ])
                    ->buttons([
                        ActionButton::make('Открыть', fn (CustomerRequest $request): string => $this->pageUrl(CustomerRequestFormPage::class, CustomerRequestResource::class, ['resourceItem' => $request->getKey()])),
                    ])
                    ->withNotFound(),
                ActionButton::make('Все заявки', $this->pageUrl(CustomerRequestIndexPage::class, CustomerRequestResource::class)),
            ]),

            Box::make('Товары, которым не хватает данных', [
                Heading::make('Только активные товары — их видят покупатели', h: 6),
                ...$this->dataQualityLinks(),
            ]),
        ];
    }

    /**
     * Ссылки на список товаров с готовым фильтром «Без …».
     *
     * @return list<ActionButton>
     */
    private function dataQualityLinks(): array
    {
        $active = fn () => Product::query()->where('status', true);

        return collect([
            'without_image' => ['Без фото', $active()->whereNull('image')->count()],
            'without_manufacturer' => ['Без производителя', $active()->whereNull('manufacturer_id')->count()],
            'without_categories' => ['Без категории', $active()->doesntHave('categories')->count()],
        ])->map(fn (array $check, string $filter): ActionButton => ActionButton::make(
            "{$check[0]}: {$check[1]}",
            $this->pageUrl(ProductIndexPage::class, ProductResource::class, [
                'filter' => ['status' => '1', $filter => '1'],
            ]),
        ))->values()->all();
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function pageUrl(string $page, string $resource, array $params = []): string
    {
        return (string) $this->getCore()->getRouter()->getEndpoints()->toPage($page, $resource, params: $params);
    }
}
