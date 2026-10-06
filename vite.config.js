import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/storefront/base.css',
                'resources/css/storefront/app.css',
                'resources/css/storefront/pages/home.css',
                'resources/css/storefront/pages/product.css',
                'resources/css/storefront/pages/search.css',
                'resources/css/storefront/pages/cart.css',
                'resources/css/storefront/pages/contact.css',
                'resources/css/storefront/pages/compare.css',
                'resources/css/storefront/pages/account.css',
                'resources/js/storefront/app.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        // Dev-сервер работает в контейнере node (docker compose), браузер — на хосте.
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {host: 'localhost'},
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
