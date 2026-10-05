<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\DecodesLegacyText;
use App\Console\Commands\Concerns\ResolvesLegacySlug;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('import:legacy-products')]
#[Description('Импорт товаров, фото и привязки к категориям из старого проекта')]
class ImportLegacyProducts extends Command
{
    use DecodesLegacyText;
    use ResolvesLegacySlug;

    /**
     * На старом хостинге (Beget) в image/catalog/home/... случайно оказалась
     * вложенная копия всего домашнего каталога сервера — у части товаров
     * (901 из 2953) путь к фото из-за этого выглядит как
     * "catalog/home/a/aandreja/aandreja.beget.tech/public_html/image/productimg/...".
     * Файлы реальны и существуют (проверено), просто путь абсурдно длинный и
     * завязан на структуру хостинга, а не на обычный "catalog/...". Нормализуем
     * до плоского вида при импорте, чтобы это не тянулось в новый проект.
     */
    private function normalizeImagePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return preg_replace(
            '#^catalog/home/[^/]+/[^/]+/[^/]+/public_html/image/#',
            'catalog/',
            $path,
        );
    }

    public function handle(): int
    {
        $knownCategoryIds = DB::table('categories')->pluck('id')->all();
        $knownManufacturerIds = DB::table('manufacturers')->pluck('id')->all();

        $rows = DB::connection('legacy')
            ->table('oc_product as p')
            ->join('oc_product_description as pd', 'pd.product_id', '=', 'p.product_id')
            ->where('pd.language_id', 1)
            ->select('p.*', 'pd.name', 'pd.description')
            ->get();

        $this->withProgressBar($rows, function ($row) use ($knownCategoryIds, $knownManufacturerIds): void {
            $manufacturerId = in_array($row->manufacturer_id, $knownManufacturerIds, true)
                ? $row->manufacturer_id
                : null;

            DB::table('products')->updateOrInsert(
                ['id' => $row->product_id],
                [
                    'manufacturer_id' => $manufacturerId,
                    'name' => $this->legacyText($row->name),
                    'slug' => $this->resolveSlug("product_id={$row->product_id}", $this->legacyText($row->name), 'products', $row->product_id),
                    'sku' => $row->sku ?: null,
                    'code' => $row->model ?: null,
                    'description' => $this->legacyText($row->description),
                    'price' => $row->price,
                    'quantity' => $row->quantity,
                    // Остаток у поставщика и цена под заказ хранились в чужих
                    // полях: isbn и mpn (логика — в product.twig темы unishop2).
                    'supplier_quantity' => (int) $row->isbn,
                    'supplier_price' => $row->mpn !== '' ? (float) str_replace(',', '.', $row->mpn) : null,
                    'image' => $this->normalizeImagePath($row->image ?: null),
                    'status' => (bool) $row->status,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            // Доп. фото товара
            DB::table('product_images')->where('product_id', $row->product_id)->delete();
            $images = DB::connection('legacy')
                ->table('oc_product_image')
                ->where('product_id', $row->product_id)
                ->get();

            foreach ($images as $image) {
                DB::table('product_images')->insert([
                    'product_id' => $row->product_id,
                    'path' => $this->normalizeImagePath($image->image),
                    'sort_order' => $image->sort_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Привязка к категориям (many-to-many) — пропускаем ссылки на
            // категории, которых почему-то нет в новой таблице (сиротские записи).
            DB::table('category_product')->where('product_id', $row->product_id)->delete();
            $categoryIds = DB::connection('legacy')
                ->table('oc_product_to_category')
                ->where('product_id', $row->product_id)
                ->pluck('category_id')
                ->intersect($knownCategoryIds);

            foreach ($categoryIds as $categoryId) {
                DB::table('category_product')->insert([
                    'product_id' => $row->product_id,
                    'category_id' => $categoryId,
                ]);
            }
        });

        $this->newLine(2);
        $this->info("Импортировано товаров: {$rows->count()}");

        // Категории-бренды старого проекта сразу переводим в производителей —
        // иначе отдельный запуск этой команды сбросил бы бренды товаров.
        $this->call('import:legacy-brands');

        return self::SUCCESS;
    }
}
