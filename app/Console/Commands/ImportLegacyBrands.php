<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('import:legacy-brands')]
#[Description('Перенос категорий-брендов старого проекта (Аккумуляторы → TITAN и т.п.) в производителей')]
class ImportLegacyBrands extends LegacyImportCommand
{
    /**
     * Разделы, подкатегории которых в старом проекте были брендами: бренд АКБ
     * задавался не через oc_manufacturer (там 11 марок масел), а тем, в какой
     * подкатегории лежит товар.
     */
    private const BRAND_PARENT_IDS = [
        1,   // Аккумуляторы
        2,   // Мото аккумуляторы
        3,   // Грузовые аккумуляторы
        5,   // Стеклоочистители
        8,   // Лампы
        10,  // Свечи зажигания
        254, // Лодочные аккумуляторы
        257, // ИБП
        259, // Вело аккумуляторы
    ];

    /**
     * Подкатегории внутри этих разделов, которые брендами не являются.
     */
    private const NOT_BRAND_IDS = [169]; // Прочие

    /**
     * Пустые выключенные категории — остатки попытки сделать фильтр
     * категориями до OCFilter. Заменены характеристиками товаров.
     */
    private const PSEUDO_FILTER_IDS = [
        184, // Ёмкость (Ah)
        188, // Габариты (мм)
        189, // Технология
    ];

    /**
     * Категории, вложенные в бренд: при удалении бренда поднимаем в раздел.
     *
     * @var array<int, int>
     */
    private const REPARENT = [
        198 => 5, // ALCA → Адаптеры  ⇒  Стеклоочистители → Адаптеры
    ];

    /**
     * Линейки и технические варианты сводим к одному бренду — линейка и так
     * есть в названии товара. Ключ — название категории в верхнем регистре.
     *
     * @var array<string, string>
     */
    private const BRAND_ALIASES = [
        'XTREME CLASSIC' => 'XTREME',
        'XTREME EFB' => 'XTREME',
        'XTREME SILVER' => 'XTREME',
        'XTREME ARCTIC' => 'XTREME',
        'XTREME VRLA' => 'XTREME',
        'ТЮМЕНЬ МОТО' => 'ТЮМЕНЬ',
        'ТЮМЕНСКИЙ МЕДВЕДЬ' => 'ТЮМЕНЬ',
        'TYUMEN BATBEAR' => 'ТЮМЕНЬ',
        'МЕДВЕДЬ' => 'ТЮМЕНЬ',
        'AKOM +EFB' => 'AKOM',
        'ЗВЕРЬ MOTO' => 'ЗВЕРЬ',
        'BOLK MOTO' => 'BOLK',
        'OUTDO VRLA' => 'OUTDO',
    ];

    protected function import(): int
    {
        // Производители из oc_manufacturer должны быть на месте до того, как
        // мы начнём искать/создавать бренды: иначе на чистой базе HYUNDAI
        // создался бы здесь, а потом второй раз — импортом производителей.
        $this->callSilently('import:legacy-manufacturers');

        foreach (self::REPARENT as $categoryId => $parentId) {
            DB::table('categories')->where('id', $categoryId)->update(['parent_id' => $parentId]);
        }

        // Бренды и привязки товаров берём из старой базы, а не из локальных
        // таблиц: импорт товаров перезаписывает category_product без
        // категорий-брендов (их здесь уже нет), и восстановить бренд было бы
        // не из чего. Так шаг можно запускать после любого импорта.
        $brandCategories = DB::connection('legacy')
            ->table('oc_category as c')
            ->join('oc_category_description as cd', 'cd.category_id', '=', 'c.category_id')
            ->where('cd.language_id', 1)
            ->whereIn('c.parent_id', self::BRAND_PARENT_IDS)
            ->whereNotIn('c.category_id', [...self::NOT_BRAND_IDS, ...self::PSEUDO_FILTER_IDS, ...array_keys(self::REPARENT)])
            // Сначала основной раздел — его написание бренда берём за эталон
            // ("BOSCH" из аккумуляторов, а не "Bosch" из ламп).
            ->orderBy('c.parent_id')
            ->orderBy('c.category_id')
            ->select('c.category_id as id', 'c.parent_id', 'c.image', 'cd.name')
            ->get();

        $productLinks = DB::connection('legacy')
            ->table('oc_product_to_category')
            ->whereIn('category_id', $brandCategories->pluck('id'))
            ->get()
            ->groupBy('category_id');

        $existingProductIds = DB::table('products')->pluck('id')->flip();
        $existingCategoryIds = DB::table('categories')->pluck('id')->flip();

        $linkedProducts = 0;

        DB::transaction(function () use ($brandCategories, $productLinks, $existingProductIds, $existingCategoryIds, &$linkedProducts): void {
            foreach ($brandCategories as $category) {
                $manufacturerId = $this->resolveManufacturer($category->name, $category->image ?: null);
                $productIds = collect($productLinks->get($category->id, []))
                    ->pluck('product_id')
                    ->filter(fn ($productId) => isset($existingProductIds[$productId]))
                    ->values();

                DB::table('products')
                    ->whereIn('id', $productIds)
                    ->whereNull('manufacturer_id')
                    ->update(['manufacturer_id' => $manufacturerId, 'updated_at' => now()]);

                // Товар остаётся в разделе (например, «Аккумуляторы»), даже если
                // в legacy был привязан только к категории-бренду.
                if (isset($existingCategoryIds[$category->parent_id])) {
                    DB::table('category_product')->insertOrIgnore(
                        $productIds->map(fn ($productId) => [
                            'category_id' => $category->parent_id,
                            'product_id' => $productId,
                        ])->all(),
                    );
                }

                $linkedProducts += $productIds->count();
            }

            DB::table('categories')
                ->whereIn('id', [...$brandCategories->pluck('id'), ...self::PSEUDO_FILTER_IDS])
                ->delete();
        });

        $this->info("Категорий-брендов перенесено в производителей: {$brandCategories->count()}");
        $this->info("Товаров получили производителя: {$linkedProducts}");

        return self::SUCCESS;
    }

    /**
     * Ищем производителя без учёта регистра — среди уже существующих тоже
     * (HYUNDAI есть и в oc_manufacturer, и категорией-брендом АКБ).
     */
    private function resolveManufacturer(string $categoryName, ?string $image): int
    {
        $name = trim($categoryName);
        $name = self::BRAND_ALIASES[mb_strtoupper($name)] ?? $name;

        $manufacturer = DB::table('manufacturers')
            ->whereRaw('UPPER(name) = ?', [mb_strtoupper($name)])
            ->first();

        if ($manufacturer) {
            if (! $manufacturer->image && $image) {
                DB::table('manufacturers')->where('id', $manufacturer->id)->update(['image' => $image]);
            }

            return $manufacturer->id;
        }

        return DB::table('manufacturers')->insertGetId([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'image' => $image,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $slug = Str::slug($name) ?: (string) Str::uuid();
        $original = $slug;
        $i = 2;

        while (DB::table('manufacturers')->where('slug', $slug)->exists()) {
            $slug = $original.'-'.$i++;
        }

        return $slug;
    }
}
