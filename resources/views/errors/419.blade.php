@extends('layouts.app')

@section('title', 'Страница устарела — '.config('shop.name'))

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
