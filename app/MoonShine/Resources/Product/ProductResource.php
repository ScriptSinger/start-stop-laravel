<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Product;

use App\Models\Manufacturer;
use App\Models\Product;
use App\MoonShine\Fields\Money;
use App\MoonShine\Handlers\SafeImportHandler;
use App\MoonShine\Resources\Concerns\ResetsPageOutOfRange;
use App\MoonShine\Resources\Product\Pages\ProductDetailPage;
use App\MoonShine\Resources\Product\Pages\ProductFormPage;
use App\MoonShine\Resources\Product\Pages\ProductIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\Handlers\Handler;
use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\ExportHandler;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use RuntimeException;

/**
 * @extends ModelResource<Product, ProductIndexPage, ProductFormPage, ProductDetailPage>
 */
class ProductResource extends ModelResource implements HasImportExportContract
{
    use ImportExportConcern;
    use ResetsPageOutOfRange;

    protected string $model = Product::class;

    protected string $title = 'Товары';

    protected string $column = 'name';

    protected array $with = ['manufacturer', 'categories'];

    /**
     * @return string[]
     */
    protected function search(): array
    {
        return ['id', 'name', 'code', 'sku'];
    }

    /**
     * Выгрузка — с текущими фильтрами и сортировкой списка.
     */
    protected function export(): ?Handler
    {
        return ExportHandler::make('Экспорт в Excel');
    }

    protected function import(): ?Handler
    {
        return SafeImportHandler::make('Импорт из Excel')
            ->hint('Файл — выгрузка «Экспорт в Excel» с вашими правками. Товары находятся по колонке ID; новые импортом не создаются.');
    }

    /**
     * @return list<FieldContract>
     */
    protected function exportFields(): iterable
    {
        return [
            ID::make(),
            Text::make('Название', 'name'),
            Text::make('Код товара', 'code'),
            Text::make('Артикул', 'sku'),
            // Название по id из справочника, загруженного один раз: выгрузка идёт
            // курсором, без загрузки связей.
            Text::make('Производитель', 'manufacturer_id')
                ->modifyRawValue(fn (mixed $id): string => (string) once(fn () => Manufacturer::query()->pluck('name', 'id'))->get($id)),
            ...$this->editableFields(),
        ];
    }

    /**
     * Что можно править в Excel и загрузить обратно. Производитель,
     * категории и характеристики — связи, их правят массовыми кнопками.
     *
     * @return list<FieldContract>
     */
    protected function importFields(): iterable
    {
        return [
            ID::make(),
            Text::make('Название', 'name'),
            Text::make('Код товара', 'code')->nullable(),
            Text::make('Артикул', 'sku')->nullable(),
            ...$this->editableFields(),
        ];
    }

    /**
     * @return list<FieldContract>
     */
    private function editableFields(): array
    {
        return [
            // Пакет считает 0 пустым значением и подставляет default —
            // без него в базу ушёл бы NULL.
            Money::make('Цена', 'price')->default(0),
            Money::make('Цена по акции', 'special_price')->nullable(),
            Number::make('Остаток', 'quantity')->default(0),
            Number::make('Остаток у поставщика', 'supplier_quantity')->default(0),
            Money::make('Цена под заказ', 'supplier_price')->nullable(),
            Money::make('Скидка за трейд-ин', 'trade_in_discount')->nullable(),
            Switcher::make('Только самовывоз', 'is_pickup_only')->default(false),
            Switcher::make('Активен', 'status')->default(false),
        ];
    }

    /**
     * Импорт только правит существующие товары: строка без ID или с чужим
     * ID останавливает загрузку целиком (она идёт одной транзакцией).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function beforeImportFilling(array $data): array
    {
        if (blank($data['id'] ?? null)) {
            throw new RuntimeException('в файле есть строка без ID — новые товары импортом не создаются');
        }

        if (! Product::query()->whereKey($data['id'])->exists()) {
            throw new RuntimeException("товара с ID {$data['id']} нет в базе");
        }

        return $data;
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            ProductIndexPage::class,
            ProductFormPage::class,
            ProductDetailPage::class,
        ];
    }
}
