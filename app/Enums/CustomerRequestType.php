<?php

namespace App\Enums;

use MoonShine\Support\Enums\Color;

/**
 * Виды заявок с сайта — как типы oc_uni_request старого сайта.
 */
enum CustomerRequestType: string
{
    case Callback = 'callback';
    case Question = 'question';
    case ProductQuestion = 'product_question';

    public function toString(): string
    {
        return match ($this) {
            self::Callback => 'Заказ звонка',
            self::Question => 'Задать вопрос',
            self::ProductQuestion => 'Вопрос о товаре',
        };
    }

    /**
     * Цвет бейджа в админке: звонок — синий (перезвонить), вопросы —
     * фиолетовый, о товаре — жёлтый (часто это готовый покупатель).
     */
    public function getColor(): Color
    {
        return match ($this) {
            self::Callback => Color::BLUE,
            self::Question => Color::PURPLE,
            self::ProductQuestion => Color::YELLOW,
        };
    }
}
