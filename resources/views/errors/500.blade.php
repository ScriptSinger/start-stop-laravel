@extends('errors.minimal')

@section('title', 'Ошибка на сайте')

@section('content')
    @include('errors.partials.message', [
        'code' => 500,
        'message' => 'На сайте что-то пошло не так. Мы уже разбираемся — попробуйте обновить страницу чуть позже или оформите заказ по телефону.',
    ])
@endsection
