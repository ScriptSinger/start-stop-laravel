<?php

namespace App\Enums;

/**
 * Подборки товаров на главной.
 */
enum ProductSelection: string
{
    case Recommended = 'recommended';
    case Promo = 'promo';

    public function toString(): string
    {
        return match ($this) {
            self::Recommended => 'Рекомендуем',
            self::Promo => 'Акции',
        };
    }

    /**
     * Ссылка в заголовке блока на главной, как на старом сайте.
     *
     * @return array{title: string, category: string}|null
     */
    public function headingLink(): ?array
    {
        return match ($this) {
            self::Recommended => ['title' => 'Все аккумуляторы', 'category' => 'akkumulyatori'],
            self::Promo => null,
        };
    }
}
