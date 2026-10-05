<?php

namespace App\Enums;

/**
 * Где на витрине выводится меню.
 */
enum MenuLocation: string
{
    /** Тонкая полоса ссылок над шапкой (О компании, Политика…). */
    case TopLinks = 'top_links';

    /** Горизонтальное меню рядом с «Категориями» (Акции, Услуги…). */
    case Main = 'main';

    /** Колонки подвала: пункт верхнего уровня — заголовок колонки. */
    case Footer = 'footer';

    public function toString(): string
    {
        return match ($this) {
            self::TopLinks => 'Ссылки над шапкой',
            self::Main => 'Главное меню',
            self::Footer => 'Подвал',
        };
    }
}
