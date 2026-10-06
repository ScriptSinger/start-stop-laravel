@extends('layouts.app')

{{-- Своего контроллера у страницы ошибки нет — заголовок задаём здесь. --}}
@php(Artesaos\SEOTools\Facades\SEOTools::setTitle('Страница не найдена'))
@php(Artesaos\SEOTools\Facades\SEOMeta::setRobots('noindex, follow'))

@section('content')
    <div id="error-not-found" class="container">
        @include('errors.partials.message', [
            'code' => 404,
            'message' => 'Запрашиваемая страница не найдена! Возможно, она переехала — загляните в каталог или воспользуйтесь поиском.',
        ])
    </div>
@endsection
