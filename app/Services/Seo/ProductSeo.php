<?php

namespace App\Services\Seo;

use App\Models\Product;
use App\Services\Catalog\ProductMedia;
use Artesaos\SEOTools\Facades\JsonLdMulti;
use Artesaos\SEOTools\Facades\OpenGraph;
use Artesaos\SEOTools\Facades\SEOTools;

/**
 * Теги <head> карточки товара: title и description из полей товара,
 * превью ссылки с фото и микроразметка schema.org (Product с ценой и
 * наличием, BreadcrumbList) — по ней Яндекс и Google показывают цену
 * и «хлебные крошки» прямо в выдаче.
 */
class ProductSeo
{
    public function __construct(private BreadcrumbsSeo $breadcrumbs) {}

    public function apply(Product $product): void
    {
        $heading = $product->heading ?: $product->name;

        SEOTools::setTitle($product->meta_title ?: $heading, appendDefault: ! $product->meta_title);

        $description = $product->meta_description ?: $this->description($product, $heading);

        SEOTools::setDescription($description);

        OpenGraph::setType('product');

        if ($product->image) {
            SEOTools::addImages(ProductMedia::url($product->image));
        }

        JsonLdMulti::setType('Product')
            ->setTitle($heading)
            ->addValues(array_filter([
                'description' => $description,
                'image' => $this->images($product) ?: null,
                'sku' => $product->sku ?: null,
                'brand' => $product->manufacturer ? ['@type' => 'Brand', 'name' => $product->manufacturer->name] : null,
                'offers' => [
                    '@type' => 'Offer',
                    'url' => route('product.show', $product),
                    'price' => number_format($product->displayPrice(), 2, '.', ''),
                    'priceCurrency' => 'RUB',
                    'priceValidUntil' => now()->addMonth()->toDateString(),
                    'itemCondition' => 'https://schema.org/NewCondition',
                    'availability' => $this->availability($product),
                ],
            ]));

        $this->breadcrumbs($product);
    }

    /**
     * Фото товара: главное и из галереи, без заглушки «нет фото».
     *
     * @return list<string>
     */
    private function images(Product $product): array
    {
        return collect([$product->image, ...$product->images->pluck('path')])
            ->filter(fn (?string $path): bool => filled($path) && $path !== 'no_image.png')
            ->unique()
            ->map(fn (string $path): string => ProductMedia::url($path))
            ->values()
            ->all();
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
     * Описание для сниппета, когда в карточке своего нет: название, цена,
     * главные характеристики и условия магазина.
     */
    private function description(Product $product, string $heading): string
    {
        $price = number_format($product->displayPrice(), 0, '', ' ');
        $specifications = $product->specifications(3, visibleOnly: true)
            ->map(fn (string $value, string $name): string => "{$name}: {$value}")
            ->implode(', ');

        return trim("{$heading} — купить в Уфе за {$price} ₽. ".($specifications === '' ? '' : "{$specifications}. ").'Доставка по городу, гарантия, трейд-ин старого аккумулятора.');
    }

    /**
     * Те же крошки, что над заголовком карточки: главная, категория, товар.
     */
    private function breadcrumbs(Product $product): void
    {
        $category = $product->categories->first();

        $this->breadcrumbs->apply(array_values(array_filter([
            ['name' => config('shop.name'), 'url' => route('home')],
            $category ? ['name' => $category->name, 'url' => route('category.show', $category)] : null,
            ['name' => $product->heading ?: $product->name, 'url' => route('product.show', $product)],
        ])));
    }
}
