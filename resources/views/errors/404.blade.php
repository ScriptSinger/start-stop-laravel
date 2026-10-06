@extends('layouts.app')

@section('title', 'Страница не найдена — '.config('shop.name'))

@section('content')
    <div id="error-not-found" class="container">
        @include('errors.partials.message', [
            'code' => 404,
            'message' => 'Запрашиваемая страница не найдена! Возможно, она переехала — загляните в каталог или воспользуйтесь поиском.',
        ])
    </div>
@endsection
