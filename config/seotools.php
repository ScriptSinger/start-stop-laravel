<?php

/**
 * Теги <head> витрины: title, description, canonical, Open Graph и JSON-LD.
 * Страницу описывает её контроллер через фасад SEOTools/SEOMeta; название
 * магазина и логотип для превью подставляет AppServiceProvider из shop.php.
 *
 * @see https://github.com/artesaos/seotools
 */
return [
    'inertia' => env('SEO_TOOLS_INERTIA', false),
    'meta' => [
        'defaults' => [
            // Дописывается к заголовку страницы: «Корзина — Старт-Стоп».
            'title' => false,
            'titleBefore' => false,
            'description' => false,
            'separator' => ' — ',
            'keywords' => [],
            // Адрес без query-строки: фильтры, сортировка и число товаров на
            // странице — варианты одной страницы, в индекс идёт она сама
            // (так было и на старом сайте). Пагинация задаёт canonical сама.
            'canonical' => 'current',
            'robots' => false,
        ],
        'webmaster_tags' => [
            'google' => null,
            'bing' => null,
            'alexa' => null,
            'pinterest' => null,
            'yandex' => null,
            'norton' => null,
        ],

        'add_notranslate_class' => false,
    ],
    'opengraph' => [
        'defaults' => [
            'title' => false,
            'description' => false,
            'url' => null,
            'type' => 'website',
            'site_name' => false,
            'images' => [],
        ],
    ],
    'twitter' => [
        'defaults' => [],
    ],
    'json-ld' => [
        'defaults' => [
            'title' => false,
            'description' => false,
            'url' => 'current',
            'type' => 'WebPage',
            'images' => [],
        ],
    ],
];
