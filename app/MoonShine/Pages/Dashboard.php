<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\CustomerRequest;
use App\Models\Order;
use App\Models\Product;
use App\MoonShine\Components\OrdersChart;
use App\MoonShine\Resources\Order\OrderResource;
use App\MoonShine\Resources\Order\Pages\OrderIndexPage;
use App\MoonShine\Resources\Product\Pages\ProductIndexPage;
use App\MoonShine\Resources\Product\ProductResource;
use App\Services\Catalog\AssortmentGaps;
use App\Services\Catalog\StockSummary;
use Illuminate\Support\Facades\DB;
use MoonShine\Apexcharts\Components\DonutChartMetric;
use MoonShine\Apexcharts\Support\SeriesItem;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Crud\Components\Fragment;
use MoonShine\Laravel\Pages\Page;
use MoonShine\MenuManager\Attributes\SkipMenu;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\UI\Components\Collapse;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Div;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Link;
use MoonShine\UI\Fields\Select;

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
     * Графики заказов и каталога, что требует внимания
     * (только ненулевое) и лидеры продаж. Таблицы заказов и заявок — в их
     * разделах меню, новые там подсвечены счётчиком; склад подробно — на
     * странице «Остатки».
     *
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        return [
            Collapse::make('Показатели', [Grid::make([
                Column::make([Box::make([
                    FormBuilder::make()->hideSubmit()->fields([
                        Select::make('Категория', 'catalog_scope')
                            ->options($this->catalogCategoryOptions())
                            ->default('all')
                            ->onChangeEvent(AlpineJs::event(JsEvent::FRAGMENT_UPDATED, 'catalog-chart')),
                    ]),
                    Fragment::make([$this->catalogChart($this->catalogCategoryIds())->withoutWrapper()])->name('catalog-chart'),
                ])->class('h-full')], colSpan: 6, adaptiveColSpan: 12),
                Column::make([Box::make([$this->ordersChart()->withoutWrapper()])->class('h-full')], colSpan: 6, adaptiveColSpan: 12),
            ])])->open(),
            Grid::make([
                Column::make([$this->attention()], colSpan: 6, adaptiveColSpan: 12),
                Column::make([$this->bestsellers()], colSpan: 6, adaptiveColSpan: 12),
            ])->customAttributes(['style' => 'margin-top: 0.5rem']),
        ];
    }

    private function ordersChart(): OrdersChart
    {
        $start = now()->startOfDay()->subDays(29);
        $orders = Order::query()
            ->where('created_at', '>=', $start)
            ->selectRaw('date(created_at) as day, status, count(*) as total')
            ->groupByRaw('date(created_at), status')
            ->get();
        $requests = CustomerRequest::query()
            ->where('created_at', '>=', $start)
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->groupByRaw('date(created_at)')
            ->pluck('total', 'day');

        $confirmed = $abandoned = $callbacks = [];
        for ($day = $start->copy(); $day->lte(now()); $day->addDay()) {
            $date = $day->toDateString();
            $dayOrders = $orders->where('day', $date);
            $confirmed[$date] = (int) $dayOrders->filter(fn (Order $order): bool => ! in_array($order->status, [OrderStatus::Abandoned, OrderStatus::Cancelled], true))->sum('total');
            $abandoned[$date] = (int) $dayOrders->where('status', OrderStatus::Abandoned)->sum('total');
            $callbacks[$date] = (int) ($requests[$date] ?? 0);
        }

        $tooltip = view('components.admin-tooltip', ['content' => 'Заказы распределены по дате создания и текущему статусу.'])->render();

        return OrdersChart::make('Заказы и заявки за 30 дней '.$tooltip)
            ->series([
                SeriesItem::make('Оформленные заказы', $confirmed),
                SeriesItem::make('Брошенные оформления', $abandoned),
                SeriesItem::make('Заявки', $callbacks),
            ])
            ->colors(['#2563eb', '#d97706', '#9333ea']);
    }

    /** @return array<string, string> */
    private function catalogCategoryOptions(): array
    {
        $categories = Category::query()->orderBy('name')->get(['id', 'parent_id', 'name', 'status']);
        $parents = $categories->keyBy('id');
        $options = ['all' => 'Все товары', 'batteries' => 'Все аккумуляторы'];

        foreach ($categories as $category) {
            $parent = $parents->get($category->parent_id);
            $options['category_'.$category->id] = ($parent ? $parent->name.' / ' : '').$category->name.($category->status ? '' : ' (неактивная)');
        }

        return $options;
    }

    /** @return list<int>|null */
    private function catalogCategoryIds(): ?array
    {
        $scope = request()->input('_data.catalog_scope', request()->input('catalog_scope'));
        if ($scope === 'batteries') {
            $rootIds = (array) config('shop.battery_fitment.category_ids');
        } elseif (is_string($scope) && preg_match('/^category_([1-9][0-9]*)$/D', $scope, $matches)) {
            $rootIds = [(int) $matches[1]];
        } else {
            return null;
        }

        $categories = Category::query()->get(['id', 'parent_id']);
        $ids = $categories->whereIn('id', $rootIds)->pluck('id')->unique()->values()->all();
        if ($ids === [] && $scope !== 'batteries') {
            return null;
        }

        $children = $categories->groupBy('parent_id');
        for ($i = 0; $i < count($ids); $i++) {
            foreach ($children->get($ids[$i], collect()) as $child) {
                if (! in_array($child->id, $ids, true)) {
                    $ids[] = $child->id;
                }
            }
        }

        return $ids;
    }

    /** @param list<int>|null $categoryIds */
    private function catalogChart(?array $categoryIds = null): DonutChartMetric
    {
        $stock = app(StockSummary::class)->overall($categoryIds);
        $total = Product::query()
            ->when($categoryIds !== null, fn ($query) => $query->whereHas('categories', fn ($categories) => $categories->whereIn('categories.id', $categoryIds)))
            ->count();

        return DonutChartMetric::make($categoryIds === null ? 'Структура каталога' : (request()->input('_data.catalog_scope', request()->input('catalog_scope')) === 'batteries' ? 'Аккумуляторы' : 'Выбранная категория'))
            ->values([
                'Неактивные — '.($total - $stock['total']) => $total - $stock['total'],
                'Активные, нет в наличии — '.$stock['out_of_stock'] => $stock['out_of_stock'],
                'Активные, на складе магазина — '.$stock['in_stock'] => $stock['in_stock'],
                'Активные, под заказ — '.$stock['on_order'] => $stock['on_order'],
            ])
            ->colors(['#9ca3af', '#d97706', '#2563eb', '#9333ea'])
            ->height(260)
            ->decimals(0);
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
