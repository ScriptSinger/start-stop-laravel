@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li><a href="{{ route('car-landing.index') }}">По марке авто</a></li>
                <li>Аккумуляторы для {{ $brand->name }}</li>
            </ul>
            <h1>Аккумуляторы для {{ $brand->name }}</h1>
        </div>
        <div class="uni-wrapper">
            <p class="car-landing__intro">Выберите модель {{ $brand->name }} — покажем аккумуляторы, которые подходят по ёмкости, полярности и размерам, с ценами и наличием в Уфе.</p>
            <ul class="car-landing__models">
                @foreach ($models as $model)
                    <li>
                        <a href="{{ route('car-landing.model', [$brand->slug, $model->slug]) }}">
                            <span class="car-landing__model-name">{{ $model->fullName() }}</span>
                            <span class="car-landing__model-meta">{{ $productCounts[$model->slug] }} <x-plural :count="$productCounts[$model->slug]" forms="вариант|варианта|вариантов" /></span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endsection
