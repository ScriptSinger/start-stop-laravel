@extends('layouts.app')

{{-- Все марки с посадочными страницами: вход в «Аккумулятор для <модель>». --}}
@section('content')
    <div class="container car-landing">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li>Аккумуляторы по марке автомобиля</li>
            </ul>
            <h1>Аккумуляторы по марке автомобиля</h1>
        </div>
        <p class="car-landing__intro">Выберите марку и модель — покажем аккумуляторы, которые подходят по ёмкости, полярности, размерам и клеммам, с ценами и наличием в Уфе. Не нашли свою машину — позвоните, подберём.</p>
        <ul class="car-landing__brands">
            @foreach ($brands as $brand)
                <li>
                    <a href="{{ route('car-landing.brand', $brand->slug) }}">
                        <img src="{{ $logos[$brand->slug] }}" alt="{{ $brand->name }}" loading="lazy" width="48" height="48" />
                        <span class="car-landing__model-name">{{ $brand->name }}</span>
                        <span class="car-landing__model-meta">{{ $modelCounts[$brand->slug] }} <x-plural :count="$modelCounts[$brand->slug]" forms="модель|модели|моделей" /></span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endsection
