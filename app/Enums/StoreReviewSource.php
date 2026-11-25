<?php

namespace App\Enums;

enum StoreReviewSource: string
{
    case Yandex = 'yandex';
    case Avito = 'avito';
    case Site = 'site';
    case Other = 'other';

    public function toString(): string
    {
        return match ($this) {
            self::Yandex => 'Яндекс Карты',
            self::Avito => 'Авито',
            self::Site => 'Сайт',
            self::Other => 'Другой источник',
        };
    }
}
