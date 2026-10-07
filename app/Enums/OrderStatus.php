<?php

namespace App\Enums;

use MoonShine\Support\Enums\Color;

/**
 * Статусы заказа. Значение хранится в orders.status: у импортированных
 * заказов это название статуса из oc_order_status, поэтому значения русские.
 * Abandoned — order_status_id = 0 в OpenCart: оформление брошено на
 * полпути, старая админка такие заказы вообще не показывала.
 */
enum OrderStatus: string
{
    case New = 'new';
    case Pending = 'Ожидание';
    case Processing = 'В обработке';
    case Processed = 'Обработано';
    case Completed = 'Сделка завершена';
    case Cancelled = 'Отменено';
    case Abandoned = 'unknown';

    /**
     * Статус для заказа со старого сайта. Редкие статусы OpenCart, которых
     * у нас нет, сводятся к ближайшему: «Доставлено» — завершён, возвраты,
     * аннулирования и истёкшие — отменён.
     */
    public static function fromLegacy(?string $name): self
    {
        if ($name === null) {
            return self::Abandoned;
        }

        return self::tryFrom($name) ?? match ($name) {
            'Доставлено' => self::Completed,
            'Полностью измененный' => self::Processing,
            default => self::Cancelled,
        };
    }

    /**
     * Подпись в админке и личном кабинете; её же показывает MoonShine Enum.
     */
    public function toString(): string
    {
        return match ($this) {
            self::New => 'Новый',
            self::Abandoned => 'Не оформлен (брошен)',
            default => $this->value,
        };
    }

    /**
     * Цвет бейджа в админке: новый — синий, ждёт действий — жёлтый,
     * в работе — фиолетовый, итог — зелёный или красный, брошенный — серый.
     */
    public function getColor(): Color
    {
        return match ($this) {
            self::New => Color::BLUE,
            self::Pending => Color::YELLOW,
            self::Processing, self::Processed => Color::PURPLE,
            self::Completed => Color::GREEN,
            self::Cancelled => Color::RED,
            self::Abandoned => Color::GRAY,
        };
    }
}
