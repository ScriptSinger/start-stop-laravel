<!DOCTYPE html>
<html dir="ltr" lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=3" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('title', config('shop.name'))</title>
    @if (trim($__env->yieldContent('meta_description')) !== '')
        <meta name="description" content="@yield('meta_description')" />
        <meta property="og:description" content="@yield('meta_description')" />
    @endif
    {{-- Превью ссылки в соцсетях и мессенджерах; картинку страница может
         заменить своей (товар — фото), иначе логотип. --}}
    <meta property="og:title" content="@yield('title', config('shop.name'))" />
    <meta property="og:type" content="@yield('og_type', 'website')" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:image" content="@yield('og_image', Illuminate\Support\Facades\Storage::disk('public')->url(config('shop.logo')))" />
    <meta property="og:site_name" content="{{ config('shop.name') }}" />
    <meta name="theme-color" content="{{ config('shop.theme_color') }}" />
    <meta name="format-detection" content="telephone=no" />

    {{-- Стили в порядке старого сайта: тема (base), стили блоков страницы
         (каждая страница добавляет свои в page-styles), общие стили и наши
         дополнения (app). Вёрстка повторяет тему UniShop2 1:1. --}}
    @vite('resources/css/storefront/base.css')
    @stack('page-styles')
    @vite(['resources/css/storefront/app.css', 'resources/js/storefront/app.js'])
</head>
<body x-data class="@yield('body_class')">
    @include('partials.header')

    <main>
        @yield('content')
    </main>

    @include('partials.footer')
    @include('partials.cookie-notice')
    @include('partials.modals')

    @if (session('notice'))
        <div x-init="$store.alerts.show('success', @js(session('notice')))"></div>
    @endif
</body>
</html>
