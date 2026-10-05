<?php

namespace App\Enums;

/**
 * Способы оплаты — как на старом сайте (payment_cod, payment_bank_transfer,
 * payment_card). Онлайн-оплаты нет.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Оплата наличными',
            self::Transfer => 'Оплата переводом или по QR-коду',
            self::Card => 'Банковской картой (только самовывоз)',
        };
    }

    /**
     * Картой — только в магазине: терминала у курьера нет.
     */
    public function isAllowedFor(DeliveryMethod $delivery): bool
    {
        return $this !== self::Card || $delivery === DeliveryMethod::Pickup;
    }
}
