<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Enums\OrderStatus;
use App\Models\CustomerRequest;
use App\Models\Order;
use App\Models\Product;
use App\MoonShine\Resources\Order\OrderResource;
use App\MoonShine\Resources\Order\Pages\OrderIndexPage;
use App\MoonShine\Resources\Product\Pages\ProductIndexPage;
use App\MoonShine\Resources\Product\ProductResource;
use App\Services\Catalog\AssortmentGaps;
use App\Services\Catalog\StockSummary;
use Illuminate\Support\Facades\DB;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\MenuManager\Attributes\SkipMenu;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Div;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Link;
use MoonShine\UI\Components\Metrics\Wrapped\ValueMetric;

/**
 * Стартовая страница админки: главное одним взглядом. Уведомлений о
 * заказах нет (так решили) — новые заказы и заявки подсвечены счётчиком
 * в меню, брошенные оформления — здесь.
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
     * Минимум: ряд чисел по заказам, ряд по товарам, что требует внимания
     * (только ненулевое) и лидеры продаж. Таблицы заказов и заявок — в их
     * разделах меню, новые там подсвечены счётчиком; склад подробно — на
     * странице «Остатки».
     *
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        $sales = Order::query()
            ->whereNotIn('status', [OrderStatus::Abandoned, OrderStatus::Cancelled])
            ->where('created_at', '>=', now()->subDays(30));
        $previousSum = (float) Order::query()
            ->whereNotIn('status', [OrderStatus::Abandoned, OrderStatus::Cancelled])
            ->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])
            ->sum('total');

        return [
            Grid::make([
                $this->metric('Новые заказы', Order::query()->where('status', OrderStatus::New)->count(), 'shopping-cart'),
                $this->metric('Заявки — перезвонить', CustomerRequest::query()->where('is_processed', false)->count(), 'phone'),
                $this->metric('Брошенные оформления, 30 дн.', Order::query()->where('status', OrderStatus::Abandoned)->where('created_at', '>=', now()->subDays(30))->count(), 'exclamation-triangle'),
                $this->metric('Продажи за 30 дн. (было '.$this->money($previousSum).')', $this->money((float) (clone $sales)->sum('total')), 'banknotes'),
            ]),
            $this->catalogMetrics(),
            Grid::make([
                Column::make([$this->attention()], colSpan: 6, adaptiveColSpan: 12),
                Column::make([$this->bestsellers()], colSpan: 6, adaptiveColSpan: 12),
            ]),
        ];
    }

    /**
     * Товары: сколько можно купить, что на складе, что под заказ и сколько
     * готово к выгрузке на площадки.
     */
    private function catalogMetrics(): Grid
    {
        $stock = app(StockSummary::class)->overall();
        $available = $stock['in_stock'] + $stock['on_order'];
        $ready = Product::query()->readyForExport()->count();

        return Grid::make([
            $this->metric('В продаже (активных '.$stock['total'].')', $available, 'cube'),
            $this->metric('На складе — '.$this->plural($stock['units'], 'штука|штуки|штук').' на сумму', $this->money($stock['stock_value']), 'archive-box'),
            $this->metric('Под заказ у поставщика', $stock['on_order'], 'truck'),
            $this->metric('Готовы к выгрузке', $ready.' из '.$available, 'arrow-up-tray'),
        ]);
    }

    /**
     * Пять самых продаваемых товаров за 30 дней (по штукам), без отменённых
     * и брошенных заказов.
     */
    private function bestsellers(): Box
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotIn('orders.status', [OrderStatus::Abandoned->value, OrderStatus::Cancelled->value])
            ->where('orders.created_at', '>=', now()->subDays(30))
            ->groupBy('order_items.product_id', 'order_items.name')
            ->selectRaw('order_items.name, sum(order_items.quantity) as units, sum(order_items.total) as revenue')
            ->orderByDesc('units')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        return Box::make('Лидеры продаж, 30 дн.', $rows->isEmpty()
            ? [Heading::make('Продаж пока нет', h: 6)]
            : $rows->map(fn (object $row): Div => Div::make([
                Heading::make($row->name.' — '.$this->plural((int) $row->units, 'штука|штуки|штук').', '.$this->money((float) $row->revenue), h: 6),
            ])->class('py-1'))->all());
    }

    /**
     * Что требует внимания — только пункты, где что-то есть, ссылками на
     * готовый список.
     */
    private function attention(): Box
    {
        $active = fn () => Product::query()->where('status', true);
        $productsUrl = fn (string $filter): string => $this->pageUrl(ProductIndexPage::class, ProductResource::class, ['filter' => ['status' => '1', $filter => '1']]);
        $abandoned = Order::query()->where('status', OrderStatus::Abandoned)->where('created_at', '>=', now()->subDays(30))->count();
        $topGap = app(AssortmentGaps::class)->summary()['sizes'][0] ?? null;

        $items = collect([
            [$abandoned, $this->plural($abandoned, 'брошенное оформление|брошенных оформления|брошенных оформлений').' — оставили телефон, но не подтвердили заказ: перезвонить', $this->pageUrl(OrderIndexPage::class, OrderResource::class, ['filter' => ['status' => [OrderStatus::Abandoned->value]]])],
            [$count = $active()->missingFitmentData()->count(), $this->plural($count, 'аккумулятор невидим|аккумулятора невидимы|аккумуляторов невидимы').' для подбора — нет полярности, ёмкости или габаритов', $productsUrl('without_fitment_data')],
            [$count = $active()->whereNull('image')->count(), $this->plural($count, 'товар|товара|товаров').' без фото', $productsUrl('without_image')],
            [$count = $active()->whereNull('manufacturer_id')->count(), $this->plural($count, 'товар|товара|товаров').' без производителя', $productsUrl('without_manufacturer')],
            [$count = $active()->doesntHave('categories')->count(), $this->plural($count, 'товар|товара|товаров').' без категории', $productsUrl('without_categories')],
            [$count = $active()->withPriceProblem()->count(), $this->plural($count, 'товар|товара|товаров').' с ошибкой в цене — нулевая цена или акция не ниже обычной', $productsUrl('price_problem')],
            [$count = $active()->available()->where(fn ($query) => $query->whereNull('description')->orWhere('description', ''))->count(), $this->plural($count, 'товар|товара|товаров').' в продаже без описания — площадки и поисковики такие показывают хуже', $productsUrl('without_description')],
            [$count = Product::query()->where('status', false)->where('quantity', '>', 0)->count(), $this->plural($count, 'товар лежит|товара лежат|товаров лежат').' на складе, но скрыты с сайта', $this->pageUrl(ProductIndexPage::class, ProductResource::class, ['filter' => ['status' => '0', 'stock' => 'in']])],
            [$topGap['cars'] ?? 0, $topGap ? "{$topGap['size']}, {$topGap['polarity']} — нужен ".$this->plural($topGap['cars'], 'машине|машинам|машинам').', в ассортименте нет' : '', $this->pageUrl(StockReport::class)],
        ])->filter(fn (array $item): bool => $item[0] > 0);

        return Box::make('Требует внимания', $items->isEmpty()
            ? [Heading::make('Всё в порядке', h: 6)]
            : $items->map(fn (array $item): Div => Div::make([Link::make($item[2], $item[1].' →')])->class('py-1'))->values()->all());
    }

    private function metric(string $label, int|string $value, string $icon): Column
    {
        return Column::make([
            ValueMetric::make($label)->value($value)->icon($icon),
        ], colSpan: 3, adaptiveColSpan: 12);
    }

    /**
     * «4 товара», «1 человек оставил» — русское склонение по числу.
     */
    private function plural(int $count, string $forms): string
    {
        return $count.' '.app('translator')->getSelector()->choose($forms, $count, 'ru');
    }

    private function money(float $amount): string
    {
        return number_format($amount, 0, ',', ' ').' ₽';
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function pageUrl(string $page, ?string $resource = null, array $params = []): string
    {
        return (string) $this->getCore()->getRouter()->getEndpoints()->toPage($page, $resource, params: $params);
    }
}
