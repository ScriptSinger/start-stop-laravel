<?php

namespace App\Enums;

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
}
