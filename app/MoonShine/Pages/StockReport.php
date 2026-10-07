<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\MoonShine\Resources\Product\Pages\ProductIndexPage;
use App\MoonShine\Resources\Product\ProductResource;
use App\Services\Catalog\AssortmentGaps;
use App\Services\Catalog\StockSummary;
use Closure;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\MenuManager\Attributes\SkipMenu;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Link;
use MoonShine\UI\Components\Metrics\Wrapped\ValueMetric;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

/**
 * Остатки по активным товарам: общая сводка и разбивка по производителям
 * и категориям. Числа ведут в список товаров с готовым фильтром.
 */
#[SkipMenu]
class StockReport extends Page
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
        return $this->title ?: 'Остатки';
    }

    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        $summary = app(StockSummary::class);
        $overall = $summary->overall();

        return [
            Grid::make([
                $this->metric('В наличии, позиций', "{$overall['in_stock']} из {$overall['total']}"),
                $this->metric('Штук на складе', $overall['units']),
                $this->metric('На сумму', $this->money($overall['stock_value'])),
                $this->metric('Под заказ', $overall['on_order']),
                $this->metric('Нет в наличии', $overall['out_of_stock']),
            ]),

            Box::make([
                Heading::make('Только активные товары. Сумма — по обычной цене; «под заказ» — своего нет, у поставщика от '.config('shop.supplier_order_min_quantity').' шт.', h: 6),
                Flex::make([
                    ActionButton::make('Все в наличии', $this->productsUrl(['stock' => 'in'])),
                    ActionButton::make('Под заказ', $this->productsUrl(['stock' => 'order'])),
                    ActionButton::make('Нет в наличии', $this->productsUrl(['stock' => 'out'])),
                ])->justifyAlign('start'),
            ]),

            Box::make('По производителям', [
                $this->table($summary->byManufacturer()->all(), fn (?int $id): array => $id === null ? ['without_manufacturer' => '1'] : ['manufacturer_id' => $id]),
            ]),

            Box::make('По категориям', [
                Heading::make('Товар из нескольких категорий посчитан в каждой', h: 6),
                $this->table($summary->byCategory()->all(), fn (?int $id): array => ['categories' => [$id]]),
            ]),

            $this->assortmentGaps(),
        ];
    }

    /**
     * Машины из базы подбора без единого подходящего АКБ — что заказать.
     */
    private function assortmentGaps(): Box
    {
        $gaps = app(AssortmentGaps::class)->summary();

        return Box::make('Спрос, которого нет в ассортименте', [
            Heading::make("Подбор не находит ни одного аккумулятора для {$gaps['without_batteries']} машин из {$gaps['cars']} (без спецтехники). Нужные им типоразмеры — по числу машин; пересчёт каждую ночь.", h: 6),
            TableBuilder::make(items: $gaps['sizes'])
                ->fields([
                    Text::make('Типоразмер', 'size'),
                    Text::make('Полярность', 'polarity'),
                    Number::make('Машин', 'cars'),
                ])
                ->withNotFound(),
        ]);
    }

    private function metric(string $label, int|string $value): Column
    {
        return Column::make([
            ValueMetric::make($label)->value($value),
        ], colSpan: 2, adaptiveColSpan: 6);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  Closure(?int): array<string, mixed>  $filterFor  фильтр списка товаров по строке
     */
    private function table(array $rows, Closure $filterFor): TableBuilder
    {
        // Ссылкой — только ненулевые числа: по нулю в списке нечего смотреть.
        $linked = fn (string $label, string $column, array $stockFilter = []): Text => Text::make(
            $label,
            $column,
            fn (array $row): string => $row[$column] === 0 ? '0' : (string) Link::make(
                $this->productsUrl([...$filterFor($row['id']), ...$stockFilter]),
                (string) $row[$column],
            )->render(),
        )->unescape();

        return TableBuilder::make(items: $rows)
            ->fields([
                $linked('Название', 'name'),
                Number::make('Позиций', 'total'),
                $linked('В наличии', 'in_stock', ['stock' => 'in']),
                Number::make('Штук', 'units'),
                $linked('Под заказ', 'on_order', ['stock' => 'order']),
                $linked('Нет', 'out_of_stock', ['stock' => 'out']),
                Text::make('Сумма', 'stock_value', fn (array $row): string => $this->money($row['stock_value'])),
            ])
            ->withNotFound();
    }

    private function money(float $amount): string
    {
        return number_format($amount, 0, ',', ' ').' ₽';
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    private function productsUrl(array $filter): string
    {
        return (string) $this->getCore()->getRouter()->getEndpoints()->toPage(
            ProductIndexPage::class,
            ProductResource::class,
            params: ['filter' => ['status' => '1', ...$filter]],
        );
    }
}
