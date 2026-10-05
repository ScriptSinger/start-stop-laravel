<?php

namespace App\Enums;

/**
 * Где на витрине выводится баннер.
 */
enum BannerPosition: string
{
    /** Слайдер наверху главной (промокод, трейд-ин…). */
    case HomeSlider = 'home_slider';

    /** Узкий баннер под слайдером (бесплатная доставка). */
    case HomeStrip = 'home_strip';

    public function toString(): string
    {
        return match ($this) {
            self::HomeSlider => 'Главная: слайдер',
            self::HomeStrip => 'Главная: полоса под слайдером',
        };
    }
}
