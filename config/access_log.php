<?php

return [
    'slow_threshold_ms' => 1000,
    'suspicious_paths' => [
        'secrets' => [
            '/.env',
            '/.git',
            '/.svn',
            '/backup',
        ],
        'wordpress' => [
            '/wp-login.php',
            '/wp-admin',
            '/wp-content',
            '/wp-includes',
            '/xmlrpc.php',
        ],
        'db_tools' => [
            '/phpmyadmin',
            '/pma',
            '/adminer.php',
            '/adminer',
        ],
        'phpunit' => [
            '/vendor/phpunit',
            '/phpunit',
        ],
        'well_known' => [
            '/.well-known',
        ],
        'misc' => [
            '/config',
            '/debug',
            '/server-status',
            '/owa',
        ],
    ],
    'well_known_allowlist' => [
        '/.well-known/acme-challenge',
    ],
    'bot_patterns' => [
        'bot',
        'crawler',
        'spider',
        'slurp',
        'bingpreview',
        'bingbot',
        'duckduckbot',
        'baiduspider',
        'yandex',
        'googlebot',
        'adsbot',
        'semrush',
        'ahrefs',
        'mj12bot',
        'facebookexternalhit',
        'twitterbot',
        'linkedinbot',
        'pinterest',
    ],
];
