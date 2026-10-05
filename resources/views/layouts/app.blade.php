<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=3" />
    <title>@yield('title', config('shop.name'))</title>

    <link href="{{ asset('theme/stylesheet/bootstrap.min.css') }}" rel="stylesheet" />
    <link href="{{ asset('theme/stylesheet/opensans.css') }}" rel="stylesheet" />
    <link href="{{ asset('theme/stylesheet/font-awesome.min.css') }}" rel="stylesheet" />
    <link href="{{ asset('theme/stylesheet/animate.css') }}" rel="stylesheet" />
    <link href="{{ asset('theme/stylesheet/stylesheet.css') }}" rel="stylesheet" />
    {{-- Брендовые цвета/переменные темы (--btn-primary-bg и т.д.) — сгенерированы
    старой админкой один раз, сами по себе статичны, перенесены как есть. --}}
    <link href="{{ asset('theme/stylesheet/generated.0.css') }}" rel="stylesheet" />
    <link href="{{ asset('theme/stylesheet/generated-user-style.0.css') }}" rel="stylesheet" />
</head>
<body>
    @include('partials.header')

    <main>
        @yield('content')
    </main>

    @include('partials.footer')

    <script src="{{ asset('theme/js/jquery-2.2.4.min.js') }}"></script>
    <script src="{{ asset('theme/js/bootstrap.min.js') }}"></script>
    @stack('scripts')
</body>
</html>
