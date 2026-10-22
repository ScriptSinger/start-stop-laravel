<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Поиск дублей товаров. Дубль — товары с одинаковым названием после приведения:
 * регистр, «60 Ah» = «60Ah», «О.П.» = «оп», порядок слов, написания брендов.
 *
 * В каждой группе остаётся один товар: сначала тот, у кого есть заказы или
 * обращения, затем с external_id, sku, активный, с остатком, с категориями,
 * с большим числом характеристик, с картинками, с акционной ценой, из подбора;
 * при равенстве — с наименьшим id.
 *
 * Группа считается спорной (ничего не удаляется), если у товаров разные цены,
 * sku, «код товара» или производители, у нескольких есть external_id, у
 * удаляемого есть заказы или обращения либо у него данных больше, чем у остающегося.
 */
class ProductDuplicates
{
    private const BRAND_ALIASES = [
        'fire ball' => 'fireball',
        'euro silver' => 'eurosilver',
        'standart' => 'standard',
        'азия' => 'asia',
        'тюменский медведь' => 'тюмень',
    ];

    /**
     * @return Collection<int, array{key: string, keep: Product, duplicates: Collection<int, Product>, reasons: list<string>}>
     */
    public function groups(): Collection
    {
        $products = Product::query()->get();
        $usage = $this->usage($products);

        return $products
            ->groupBy(fn (Product $product): string => $this->key($product->name))
            ->reject(fn (Collection $group, string $key): bool => $key === '' || $group->count() < 2)
            ->map(function (Collection $group, string $key) use ($usage): array {
                $sorted = $group->sortByDesc(fn (Product $product): array => $this->priority($product, $usage[$product->id]))->values();

                return [
                    'key' => $key,
                    'keep' => $sorted->first(),
                    'duplicates' => $sorted->slice(1)->values(),
                    'reasons' => $this->reasons($sorted, $usage),
                ];
            })
            ->sortBy('key')
            ->values();
    }

    /**
     * Приведение названия к виду, одинаковому у дублей.
     */
    public function key(string $name): string
    {
        $name = mb_strtolower(str_replace(['ё', 'Ё'], 'е', $name));
        $name = (string) preg_replace('/(\d)\s*(ah|ач)\b/u', '$1ah', $name);
        $name = (string) preg_replace('/\bо\s*\.?\s*п\b\.?/u', 'оп', $name);
        $name = (string) preg_replace('/\bп\s*\.?\s*п\b\.?/u', 'пп', $name);
        $name = strtr($name, self::BRAND_ALIASES);

        preg_match_all('/[a-zа-я0-9]+/u', $name, $matches);
        $words = $matches[0];
        sort($words);

        return implode(' ', $words);
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<int, array{used: int, selection: int, categories: int, attributes: int, images: int}>
     */
    private function usage(Collection $products): array
    {
        $count = fn (string $table, bool $onlyLinked = false): Collection => DB::table($table)
            ->when($onlyLinked, fn ($query) => $query->whereNotNull('product_id'))
            ->selectRaw('product_id, count(*) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        $orders = $count('order_items', true);
        $requests = $count('customer_requests', true);
        $selection = $count('product_selection');
        $categories = $count('category_product');
        $attributes = $count('attribute_value_product');
        $images = $count('product_images');

        return $products->mapWithKeys(fn (Product $product): array => [$product->id => [
            'used' => (int) ($orders[$product->id] ?? 0) + (int) ($requests[$product->id] ?? 0),
            'selection' => (int) ($selection[$product->id] ?? 0),
            'categories' => (int) ($categories[$product->id] ?? 0),
            'attributes' => (int) ($attributes[$product->id] ?? 0),
            'images' => (int) ($images[$product->id] ?? 0),
        ]])->all();
    }

    /**
     * @param  array{used: int, selection: int, categories: int, attributes: int, images: int}  $usage
     * @return list<int|bool>
     */
    private function priority(Product $product, array $usage): array
    {
        return [
            $usage['used'] > 0,
            filled($product->external_id),
            filled($product->sku),
            (bool) $product->status,
            $product->quantity > 0,
            $usage['categories'] > 0,
            $usage['attributes'],
            $usage['images'] > 0,
            $product->special_price !== null,
            $usage['selection'] > 0,
            -$product->id,
        ];
    }

    /**
     * @param  Collection<int, Product>  $sorted
     * @param  array<int, array{used: int, selection: int, categories: int, attributes: int, images: int}>  $usage
     * @return list<string>
     */
    private function reasons(Collection $sorted, array $usage): array
    {
        $keep = $sorted->first();
        $duplicates = $sorted->slice(1);
        $reasons = [];

        if ($sorted->pluck('price')->map(fn ($price): string => (string) $price)->unique()->count() > 1) {
            $reasons[] = 'разная цена';
        }

        if ($sorted->pluck('code')->filter()->unique()->count() > 1) {
            $reasons[] = 'разный «код товара»';
        }

        if ($sorted->pluck('manufacturer_id')->filter()->unique()->count() > 1) {
            $reasons[] = 'разные производители';
        }

        if ($sorted->pluck('external_id')->filter()->count() > 1) {
            $reasons[] = 'несколько external_id';
        }

        if ($sorted->pluck('sku')->filter()->unique()->count() > 1) {
            $reasons[] = 'разные sku';
        }

        if ($duplicates->contains(fn (Product $product): bool => $usage[$product->id]['used'] > 0)) {
            $reasons[] = 'у дубля есть заказы или обращения';
        }

        $keepHasContent = $usage[$keep->id]['categories'] > 0 || $usage[$keep->id]['attributes'] > 0;

        if (! $keepHasContent && $duplicates->contains(fn (Product $product): bool => $this->hasContent($product, $usage[$product->id]))) {
            $reasons[] = 'у дубля данных больше, чем у оставляемого';
        }

        return $reasons;
    }

    /**
     * @param  array{used: int, selection: int, categories: int, attributes: int, images: int}  $usage
     */
    private function hasContent(Product $product, array $usage): bool
    {
        return $usage['categories'] > 0
            || $usage['attributes'] > 0
            || $usage['images'] > 0
            || $product->status
            || $product->quantity > 0
            || $product->special_price !== null;
    }
}
