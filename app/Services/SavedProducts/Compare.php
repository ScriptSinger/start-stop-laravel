<?php

namespace App\Services\SavedProducts;

/**
 * Сравнение товаров. Как в OpenCart — не больше четырёх: пятый вытесняет
 * самый старый.
 */
class Compare extends SessionProductList
{
    public const LIMIT = 4;

    protected function sessionKey(): string
    {
        return 'compare';
    }

    protected function limit(): ?int
    {
        return self::LIMIT;
    }
}
