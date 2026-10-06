<!DOCTYPE html>
<html dir="ltr" lang="ru">
{{-- Для серверных ошибок: без шапки и подвала — их меню и счётчики читают
     базу, а она при такой ошибке может быть недоступна. --}}
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex" />
    <meta name="theme-color" content="{{ config('shop.theme_color') }}" />
    <title>@yield('title') — {{ config('shop.name') }}</title>
    @vite(['resources/css/storefront/base.css', 'resources/css/storefront/app.css'])
</head>
<body>
    <main class="container">
        <div class="error-page__logo">
            <a href="{{ url('/') }}"><img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url(config('shop.logo')) }}" alt="{{ config('shop.name') }}" width="1080" height="442" class="img-responsive" /></a>
        </div>
        @yield('content')
        <p class="error-page__contacts">
            Позвоните нам: <a href="tel:+{{ preg_replace('/\D/', '', config('shop.phone')) }}">{{ config('shop.phone') }}</a>
        </p>
    </main>
</body>
</html>
