<?php

namespace App\Enums;

/**
 * Как на сайте показывается цена услуги. Для «фикс» и «от» нужна сумма,
 * остальные типы сами дают подпись.
 */
enum ServicePriceType: string
{
    case Free = 'free';
    case Fixed = 'fixed';
    case From = 'from';
    case Negotiable = 'negotiable';
    case ByPhone = 'by_phone';

    /**
     * Подпись в админке; её же показывает MoonShine Enum.
     */
    public function toString(): string
    {
        return match ($this) {
            self::Free => 'Бесплатно',
            self::Fixed => 'Фиксированная цена',
            self::From => 'Цена «от»',
            self::Negotiable => 'По договорённости',
            self::ByPhone => 'Уточняйте по телефону',
        };
    }

    /**
     * Нужна ли сумма: только у «фикс» и «от».
     */
    public function needsAmount(): bool
    {
        return in_array($this, [self::Fixed, self::From], true);
    }

    /**
     * Подпись цены на сайте: «Бесплатно», «500 ₽», «от 500 ₽».
     */
    public function label(?int $price): string
    {
        $amount = number_format((int) $price, 0, ',', ' ').' ₽';

        return match ($this) {
            self::Free => 'Бесплатно',
            self::Fixed => $amount,
            self::From => 'от '.$amount,
            self::Negotiable => 'Стоимость согласовывается индивидуально',
            self::ByPhone => 'Условия уточняйте по телефону',
        };
    }

    /**
     * Значения типов, для которых нужна сумма (для правил валидации и showWhen).
     *
     * @return list<string>
     */
    public static function valuesWithAmount(): array
    {
        return array_values(array_map(
            fn (self $type): string => $type->value,
            array_filter(self::cases(), fn (self $type): bool => $type->needsAmount()),
        ));
    }
}
