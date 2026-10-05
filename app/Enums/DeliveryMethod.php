<?php

namespace App\Enums;

/**
 * Способы получения заказа — как на старом сайте (shipping_free под
 * названием «Доставка по городу» и shipping_pickup).
 */
enum DeliveryMethod: string
{
    case City = 'city';
    case Pickup = 'pickup';

    /**
     * Подпись для покупателя; в заказ сохраняется она же (снимок).
     */
    public function label(): string
    {
        return match ($this) {
            self::City => 'Доставка по городу',
            self::Pickup => 'Самовывоз: магазин «'.config('shop.name').'», '.config('shop.address').'. Время работы 10–20, срок хранения 3 дня',
        };
    }

    /**
     * Стоимость в рублях. На старом сайте доставка по городу была бесплатной.
     */
    public function price(): int
    {
        return 0;
    }

    public function needsAddress(): bool
    {
        return $this === self::City;
    }
}
