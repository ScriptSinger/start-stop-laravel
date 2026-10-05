@extends('layouts.app')

@php($carName = $fitment->displayName())

@section('title', 'Аккумуляторы для '.$carName.' — '.config('shop.name'))

@section('content')
    <div class="container">
        <ul class="breadcrumb">
            <li><a href="{{ route('home') }}">Главная</a></li>
            <li>Подбор АКБ</li>
            <li>{{ $carName }}</li>
        </ul>

        <h1>Аккумуляторы для {{ $carName }}</h1>

        {{-- Параметры, по которым шёл подбор, — чтобы покупатель мог сверить с машиной. --}}
        <ul class="list-unstyled" style="margin-bottom: 20px;">
            @if ($fitment->capacities() !== [])
                <li>Ёмкость: {{ implode(', ', $fitment->capacities()) }} Ач</li>
            @endif
            @if ($fitment->polarity)
                <li>Полярность: {{ $fitment->polarity }}</li>
            @endif
            @if ($fitment->dimensions() !== [])
                <li>Габариты (Д×Ш×В, мм): {{ collect($fitment->dimensions())->map(fn (array $dims) => implode('×', $dims))->implode(', ') }}</li>
            @endif
        </ul>

        @if ($products->isEmpty())
            <div class="div-text-empty">
                Подходящих аккумуляторов сейчас нет в наличии. Позвоните нам —
                <a href="tel:{{ preg_replace('/\D/', '', config('shop.phone')) }}">{{ config('shop.phone') }}</a>, подберём под заказ.
            </div>
        @else
            <div class="products-block row row-flex">
                @foreach ($products as $product)
                    @include('partials.product-card', ['product' => $product])
                @endforeach
            </div>

            <div class="pagination-wrap">
                {{ $products->links() }}
            </div>
        @endif
    </div>
@endsection
