@extends('layouts.app')

{{-- Своего контроллера у страницы ошибки нет — заголовок задаём здесь. --}}
@php(Artesaos\SEOTools\Facades\SEOTools::setTitle('Страница устарела'))
@php(Artesaos\SEOTools\Facades\SEOMeta::setRobots('noindex, follow'))

@section('content')
    <div id="error-not-found" class="container">
        @include('errors.partials.message', [
            'code' => 419,
            'message' => 'Страница была открыта слишком долго, и форма устарела. Обновите страницу и отправьте её ещё раз.',
            'actionUrl' => url()->previous(),
            'actionText' => 'Вернуться назад',
        ])
    </div>
@endsection
