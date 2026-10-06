@extends('errors.minimal')

@section('title', 'Технические работы')

@section('content')
    @include('errors.partials.message', [
        'code' => 503,
        'message' => 'На сайте идут технические работы, скоро всё заработает. Заказать аккумулятор можно по телефону.',
    ])
@endsection
