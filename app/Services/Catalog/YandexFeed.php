<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use XMLWriter;

/**
 * YML-фид каталога для Яндекса (Бизнес «Товары», Маркет, товарные кампании
 * Директа). Описание упрощённое (name + vendor): Яндекс принимает его для
 * любых товаров без обязательной модели и типа, а название у нас уже содержит
 * бренд и модель. Берём только товары, готовые к выгрузке
 * (см. Product::readyForExport()): включённые, в наличии или под заказ, с
 * ценой для покупателя, фото, производителем и разделом.
 */
class YandexFeed
{
    private const DESCRIPTION_LIMIT = 500;

    private const CHUNK_SIZE = 200;

    /**
     * Собирает фид во временный файл рядом с целевым и подменяет им прежний
     * (rename атомарен): робот Яндекса никогда не получит половину файла.
     * Возвращает число товаров в фиде.
     */
    public function store(string $path): int
    {
        $temporary = $path.'.'.Str::random(8).'.tmp';
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Не удалось создать папку {$directory}.");
        }

        try {
            $count = $this->write($temporary);

            if (! rename($temporary, $path)) {
                throw new RuntimeException("Не удалось заменить файл {$path}.");
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }

        // Временный файл создаётся с правами по umask процесса — для nginx нужно чтение.
        @chmod($path, 0644);

        return $count;
    }

    private function write(string $file): int
    {
        $xml = new XMLWriter;
        $xml->openUri($file);
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('yml_catalog');
        $xml->writeAttribute('date', now()->format('Y-m-d\TH:i:sP'));
        $xml->startElement('shop');
        $xml->writeElement('name', (string) config('shop.name'));
        $xml->writeElement('company', (string) config('shop.name'));
        $xml->writeElement('url', rtrim(url('/'), '/'));

        $xml->startElement('currencies');
        $xml->startElement('currency');
        $xml->writeAttribute('id', 'RUB');
        $xml->writeAttribute('rate', '1');
        $xml->endElement();
        $xml->endElement();

        $categories = $this->writeCategories($xml);

        $count = 0;
        $xml->startElement('offers');

        Product::query()
            ->readyForExport()
            ->with(['manufacturer:id,name', 'categories:id', 'attributeValues.attribute'])
            ->chunkById(self::CHUNK_SIZE, function (Collection $products) use ($xml, $categories, &$count): void {
                foreach ($products as $product) {
                    $categoryId = $product->categories->pluck('id')->sort()->first(fn (int $id): bool => $categories->has($id));

                    if ($categoryId === null || ! $this->writeOffer($xml, $product, $categoryId)) {
                        continue;
                    }

                    $count++;
                }
            });

        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();
        $xml->flush();

        return $count;
    }

    /**
     * Включённые разделы; parentId — только если родитель тоже в фиде.
     *
     * @return Collection<int, Category>
     */
    private function writeCategories(XMLWriter $xml): Collection
    {
        $categories = Category::query()->where('status', true)->orderBy('id')->get(['id', 'parent_id', 'name'])->keyBy('id');

        $xml->startElement('categories');

        foreach ($categories as $category) {
            $xml->startElement('category');
            $xml->writeAttribute('id', (string) $category->id);

            if ($category->parent_id !== null && $categories->has($category->parent_id) && $category->parent_id !== $category->id) {
                $xml->writeAttribute('parentId', (string) $category->parent_id);
            }

            $xml->text($this->clean($category->name));
            $xml->endElement();
        }

        $xml->endElement();

        return $categories;
    }

    private function writeOffer(XMLWriter $xml, Product $product, int $categoryId): bool
    {
        $price = $product->displayPrice();
        $name = $this->clean($product->name);

        if ($price <= 0 || $name === '') {
            return false;
        }

        $xml->startElement('offer');
        $xml->writeAttribute('id', (string) $product->id);
        $xml->writeAttribute('available', $product->quantity > 0 ? 'true' : 'false');

        $xml->writeElement('url', route('product.show', $product));
        $xml->writeElement('price', $this->formatPrice($price));
        $xml->writeElement('currencyId', 'RUB');
        $xml->writeElement('categoryId', (string) $categoryId);
        $xml->writeElement('picture', $this->pictureUrl($product));
        $xml->writeElement('name', $name);
        $xml->writeElement('vendor', $this->clean((string) $product->manufacturer?->name));

        if (filled($product->sku)) {
            $xml->writeElement('vendorCode', $this->clean((string) $product->sku));
        }

        $description = $this->description($product);

        if ($description !== '') {
            $xml->writeElement('description', $description);
        }

        foreach ($product->specifications(visibleOnly: true) as $attributeName => $value) {
            $xml->startElement('param');
            $xml->writeAttribute('name', $this->clean((string) $attributeName));
            $xml->text($this->clean($value));
            $xml->endElement();
        }

        $xml->endElement();

        return true;
    }

    private function formatPrice(float $price): string
    {
        return rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.');
    }

    private function pictureUrl(Product $product): string
    {
        $url = ProductMedia::url($product->image);

        return Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);
    }

    /**
     * Описание без разметки и лишних пробелов, не длиннее лимита Яндекса.
     */
    private function description(Product $product): string
    {
        $html = (string) preg_replace('~<(br|/p|/li|/div|/h[1-6])\b[^>]*>~i', ' ', (string) $product->description);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return Str::limit($this->clean($text), self::DESCRIPTION_LIMIT, '…', preserveWords: true);
    }

    /**
     * Убирает управляющие символы, недопустимые в XML 1.0, и схлопывает пробелы;
     * сами спецсимволы (&, <, ") экранирует XMLWriter.
     */
    private function clean(string $value): string
    {
        $value = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);

        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $value));
    }
}
