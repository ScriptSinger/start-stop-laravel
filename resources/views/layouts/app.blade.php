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
    @endif

    {{-- Стили темы UniShop2 в том же порядке, что на старом сайте: вёрстка
         повторяет её разметку 1:1, иначе CSS темы ложится криво.
         generated*.css — цвета и переменные, собранные старой админкой.
         Стили блоков конкретной страницы (home-banner, contact-page…) она
         добавляет сама через @push('module-styles') — в то же место списка. --}}
    @foreach (['bootstrap.min', 'opensans', 'stylesheet', 'generated.0', 'font-awesome.min', 'animate'] as $stylesheet)
        <link href="{{ asset("theme/stylesheet/{$stylesheet}.css") }}" rel="stylesheet" media="screen" />
    @endforeach
    @stack('module-styles')
    @foreach (['livesearch', 'flymenu', 'qty-indicator', 'topstripe', 'notification', 'blog', 'generated-user-style.0', 'storefront'] as $stylesheet)
        <link href="{{ asset("theme/stylesheet/{$stylesheet}.css") }}" rel="stylesheet" media="screen" />
    @endforeach
    <style>.uni-module__wrapper{opacity:1}</style>
    @stack('styles')
</head>
<body @if (session('notice')) data-notice="{{ session('notice') }}" @endif>
    @include('partials.header')

    <main>
        @yield('content')
    </main>

    @include('partials.footer')
    @include('partials.cookie-notice')

    <script src="{{ asset('theme/js/jquery-2.2.4.min.js') }}"></script>
    <script src="{{ asset('theme/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('theme/js/menu-aim.min.js') }}"></script>
    <script src="{{ asset('theme/js/owl.carousel.min.js') }}"></script>
    <script src="{{ asset('theme/js/jquery.maskedinput.min.js') }}"></script>
    <script src="{{ asset('theme/js/storefront.js') }}"></script>
    @stack('scripts')
</body>
</html>
