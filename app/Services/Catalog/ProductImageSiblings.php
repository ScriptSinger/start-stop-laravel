<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Фото для товаров без фото по «родственным» товарам сайта. На сайте одно фото
 * общее для серии аккумуляторов (этикетка одинакова, меняется только ёмкость),
 * поэтому:
 *  - аккумулятору берётся фото товара той же серии (бренд + серия до ёмкости:
 *    «EDCON 63Ah» ← «EDCON 77Ah»);
 *  - остальному товару — фото товара с тем же названием, отличающимся только
 *    цифрами («Щетка ALCA 17 (430мм)» ← «Щетка ALCA 13 (330мм)»), если в названии
 *    не меньше трёх слов.
 * Берётся только существующий файл; сами файлы не копируются, в `image`
 * записывается тот же путь.
 */
class ProductImageSiblings
{
    public const RULE_SERIES = 'та же серия';

    public const RULE_SAME_NAME = 'то же название, другие цифры';

    /**
     * @return Collection<int, array{product: Product, image: string, source: Product, rule: string}>
     */
    public function plan(): Collection
    {
        $disk = Storage::disk('public');

        $donors = Product::query()
            ->whereNotNull('image')->where('image', '!=', '')
            ->orderByDesc('status')->orderBy('id')
            ->with('categories:id')
            ->get(['id', 'name', 'image', 'status'])
            ->filter(fn (Product $product): bool => $disk->exists($product->image));

        $bySeries = $donors->groupBy(fn (Product $product): string => $this->series($product->name) ?? '')->forget('');
        $byName = $donors->groupBy(fn (Product $product): string => $this->wordsOnly($product->name));

        return Product::query()
            ->whereNotNull('external_id')
            ->where(fn ($query) => $query->whereNull('image')->orWhere('image', ''))
            ->with('categories:id')
            ->get(['id', 'name', 'image', 'status'])
            ->map(function (Product $product) use ($bySeries, $byName): ?array {
                if (($series = $this->series($product->name)) !== null) {
                    $source = $this->closest($product, $bySeries->get($series, collect()));

                    return $source ? ['product' => $product, 'image' => $source->image, 'source' => $source, 'rule' => self::RULE_SERIES] : null;
                }

                $words = $this->wordsOnly($product->name);
                $source = count(explode(' ', $words)) >= 3 ? $this->closest($product, $byName->get($words, collect())) : null;

                return $source ? ['product' => $product, 'image' => $source->image, 'source' => $source, 'rule' => self::RULE_SAME_NAME] : null;
            })
            ->filter()
            ->values();
    }

    /**
     * Донор из того же раздела (у SOLO есть и грузовые, и легковые АКБ — фото
     * разные); из таких — с ближайшей ёмкостью, при равенстве активный и с
     * меньшим id (порядок донора уже такой).
     *
     * @param  Collection<int, Product>  $donors
     */
    private function closest(Product $product, Collection $donors): ?Product
    {
        $categories = $product->categories->modelKeys();
        $capacity = $this->capacity($product->name);

        return $donors
            ->filter(fn (Product $donor): bool => array_intersect($categories, $donor->categories->modelKeys()) !== [])
            ->sortBy(fn (Product $donor): float => $capacity !== null && ($donorCapacity = $this->capacity($donor->name)) !== null ? abs($capacity - $donorCapacity) : 0.0)
            ->first();
    }

    private function capacity(string $name): ?float
    {
        return preg_match('/(\d+(?:[.,]\d+)?)\s*(?:ah|ач)\b/iu', $name, $match) === 1 ? (float) str_replace(',', '.', $match[1]) : null;
    }

    /**
     * Запись пути фото в товары по плану.
     *
     * @param  Collection<int, array{product: Product, image: string, source: Product, rule: string}>  $plan
     */
    public function apply(Collection $plan): int
    {
        $plan->each(fn (array $item): bool => $item['product']->update(['image' => $item['image']]));

        return $plan->count();
    }

    /**
     * Серия аккумулятора: слова названия до ёмкости («titan asia silver»). У не
     * аккумулятора (нет «NN Ah») — null.
     */
    private function series(string $name): ?string
    {
        $name = $this->normalize($name);

        if (preg_match('/^(.*?)\d+(?:[.,]\d+)?ah\b/u', $name, $match) !== 1) {
            return null;
        }

        $series = trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $match[1]));

        return $series !== '' ? $series : null;
    }

    private function wordsOnly(string $name): string
    {
        return trim((string) preg_replace('/[^\p{L}]+/u', ' ', $this->normalize($name)));
    }

    private function normalize(string $name): string
    {
        $name = mb_strtolower(str_replace(['ё', 'Ё'], 'е', $name));
        $name = (string) preg_replace('/(\d)\s*(ah|ач)\b/u', '$1ah', $name);

        return strtr($name, ['fire ball' => 'fireball', 'euro silver' => 'eurosilver', 'standart' => 'standard', 'тюменский медведь' => 'тюмень']);
    }
}
