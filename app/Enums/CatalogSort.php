<?php

namespace App\Enums;

/**
 * Сортировка товаров в каталоге — варианты старого сайта (sorts-block темы).
 */
enum CatalogSort: string
{
    case Default = 'default';
    case NameAsc = 'name';
    case NameDesc = 'name-desc';
    case PriceAsc = 'price';
    case PriceDesc = 'price-desc';

    /**
     * Без выбора — по цене от низкой к высокой, как было на старом сайте.
     */
    public static function fallback(): self
    {
        return self::PriceAsc;
    }

    public function label(): string
    {
        return match ($this) {
            self::Default => 'По умолчанию',
            self::NameAsc => 'А–Я',
            self::NameDesc => 'Я–А',
            self::PriceAsc => 'Дешёвые',
            self::PriceDesc => 'Дорогие',
        };
    }

    public function isByName(): bool
    {
        return $this === self::NameAsc || $this === self::NameDesc;
    }

    public function isByPrice(): bool
    {
        return $this === self::PriceAsc || $this === self::PriceDesc;
    }

    public function isDescending(): bool
    {
        return $this === self::NameDesc || $this === self::PriceDesc;
    }
}
