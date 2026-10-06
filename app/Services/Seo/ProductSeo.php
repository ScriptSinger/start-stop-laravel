<?php

namespace App\Services\Seo;

use App\Models\Product;
use Artesaos\SEOTools\Facades\JsonLdMulti;
use Artesaos\SEOTools\Facades\OpenGraph;
use Artesaos\SEOTools\Facades\SEOTools;
use Illuminate\Support\Facades\Storage;

/**
 * Теги <head> карточки товара: title и description из полей товара,
 * превью ссылки с фото и микроразметка schema.org (Product с ценой и
 * наличием, BreadcrumbList) — по ней Яндекс и Google показывают цену
 * и «хлебные крошки» прямо в выдаче.
 */
class ProductSeo
{
    public function apply(Product $product): void
    {
        $heading = $product->heading ?: $product->name;

        SEOTools::setTitle($product->meta_title ?: $heading, appendDefault: ! $product->meta_title);

        if (filled($product->meta_description)) {
            SEOTools::setDescription($product->meta_description);
        }

        OpenGraph::setType('product');

        if ($product->image) {
            SEOTools::addImages(Storage::disk('public')->url($product->image));
        }

        JsonLdMulti::setType('Product')
            ->setTitle($heading)
            ->addValues(array_filter([
                'sku' => $product->sku ?: null,
                'brand' => $product->manufacturer ? ['@type' => 'Brand', 'name' => $product->manufacturer->name] : null,
                'offers' => [
                    '@type' => 'Offer',
                    'url' => route('product.show', $product),
                    'price' => number_format($product->displayPrice(), 2, '.', ''),
                    'priceCurrency' => 'RUB',
                    'availability' => $this->availability($product),
                ],
            ]));

        $this->breadcrumbs($product);
    }

    private function availability(Product $product): string
    {
        return match (true) {
            $product->quantity > 0 => 'https://schema.org/InStock',
            $product->isAvailableOnOrder() => 'https://schema.org/BackOrder',
            default => 'https://schema.org/OutOfStock',
        };
    }

    /**
     * Те же крошки, что над заголовком карточки: главная, категория, товар.
     */
    private function breadcrumbs(Product $product): void
    {
        $category = $product->categories->first();

        $crumbs = array_values(array_filter([
            ['name' => config('shop.name'), 'url' => route('home')],
            $category ? ['name' => $category->name, 'url' => route('category.show', $category)] : null,
            ['name' => $product->heading ?: $product->name, 'url' => route('product.show', $product)],
        ]));

        JsonLdMulti::newJsonLd()
            ->setType('BreadcrumbList')
            ->setUrl(false)
            ->addValue('itemListElement', array_map(fn (array $crumb, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ], $crumbs, array_keys($crumbs)));
    }
}
