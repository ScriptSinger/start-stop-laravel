<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Generator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Rap2hpoutre\FastExcel\FastExcel;

/**
 * Строки выгрузки для Авито: весь ассортимент, который можно купить (в наличии
 * или под заказ). Остальные условия отбора (фото, цена, активность) не
 * применяются — их закрывают на стороне площадки.
 */
class AvitoExport
{
    /**
     * @return list<string>
     */
    public static function columns(): array
    {
        return [
            'Путь к изображению (URL)',
            'Артикул',
            'Код 1С',
            'ID товара на сайте',
            'Цена',
            'Скидка за старый',
            'Наименование',
            'Цена на сайте',
            'Кол-во',
            'Количество у поставщика',
            'Категория',
            'Подкатегория',
            'Производитель',
        ];
    }

    /**
     * @return Generator<int, array<string, scalar|null>>
     */
    public function rows(): Generator
    {
        $manufacturers = Manufacturer::query()->pluck('name', 'id');
        $categories = Category::query()->get(['id', 'parent_id', 'name'])->keyBy('id');

        foreach (Product::query()->available()->with('categories:id')->orderBy('id')->lazyById() as $product) {
            yield $this->row($product, $manufacturers, $categories);
        }
    }

    /**
     * Собирает XLSX и кладёт его на диск (локальный или S3) по указанному пути,
     * перезаписывая прежний файл. Возвращает число выгруженных товаров.
     */
    public function store(string $disk, string $path): int
    {
        $count = 0;
        $rows = (function () use (&$count): Generator {
            foreach ($this->rows() as $row) {
                $count++;

                yield $row;
            }
        })();

        $temporary = tempnam(sys_get_temp_dir(), 'avito');
        $file = $temporary.'.xlsx';

        try {
            (new FastExcel($rows))->export($file);

            $stream = fopen($file, 'rb');

            try {
                Storage::disk($disk)->put($path, $stream);
            } finally {
                fclose($stream);
            }
        } finally {
            @unlink($file);
            @unlink($temporary);
        }

        return $count;
    }

    /**
     * @param  Collection<int, string>  $manufacturers
     * @param  Collection<int, Category>  $categories
     * @return array<string, scalar|null>
     */
    private function row(Product $product, Collection $manufacturers, Collection $categories): array
    {
        [$category, $subcategory] = $this->categoryNames($product, $categories);

        return array_combine(self::columns(), [
            $this->imageUrl($product),
            $product->sku,
            $product->external_id,
            $product->id,
            (float) $product->price,
            $product->hasTradeIn() ? (float) $product->trade_in_discount : null,
            $product->name,
            $product->displayPrice(),
            $product->quantity,
            $product->supplier_quantity,
            $category,
            $subcategory,
            $manufacturers->get($product->manufacturer_id),
        ]);
    }

    /**
     * Оригинал фото с полным адресом домена; без фото ячейка пустая.
     */
    private function imageUrl(Product $product): ?string
    {
        if (blank($product->image) || $product->image === 'no_image.png') {
            return null;
        }

        $url = ProductMedia::url($product->image);

        return Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);
    }

    /**
     * Первая категория товара: «Категория» — корень её ветки, «Подкатегория» —
     * сама категория. Если товар лежит прямо в корне, подкатегории нет.
     *
     * @param  Collection<int, Category>  $categories
     * @return array{0: string|null, 1: string|null}
     */
    private function categoryNames(Product $product, Collection $categories): array
    {
        $firstId = $product->categories->sortBy('id')->first()?->id;
        $lowest = $categories->get($firstId);

        if ($lowest === null) {
            return [null, null];
        }

        $root = $lowest;

        for ($depth = 0; $root->parent_id !== null && $depth < 10; $depth++) {
            $parent = $categories->get($root->parent_id);

            if ($parent === null) {
                break;
            }

            $root = $parent;
        }

        return $root->is($lowest) ? [$root->name, null] : [$root->name, $lowest->name];
    }
}
