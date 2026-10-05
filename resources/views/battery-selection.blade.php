@extends('layouts.app')

@php($carName = $fitment->displayName())

@section('title', 'Аккумуляторы для '.$carName.' — '.config('shop.name'))

@push('module-styles')
    <link href="{{ asset('theme/stylesheet/home-banner.css') }}" rel="stylesheet" media="screen" />
@endpush

{{-- На старом сайте подбор вёл на страницу «Аккумуляторы» с фильтром OCFilter,
     поэтому раскладка — как у категории (product/category.twig темы UniShop2):
     слева выбранные параметры, справа товары. --}}
@section('content')
    <div class="container">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li><a href="{{ route('home') }}#battery-wizard">Подбор АКБ</a></li>
                <li>{{ $carName }}</li>
            </ul>
            <h1>Аккумуляторы для {{ $carName }}</h1>
        </div>
        <div class="row">
            <aside id="column-left" class="col-sm-4 col-md-3 col-lg-3 col-xxl-4">
                {{-- Параметры, по которым шёл подбор, — чтобы покупатель мог сверить с машиной. --}}
                <div class="catalog-filter" style="margin-top: 20px;">
                    <div class="heading">Ваш автомобиль</div>
                    <dl class="battery-fitment">
                        @if ($fitment->capacities() !== [])
                            <dt>Ёмкость</dt>
                            <dd>{{ implode(', ', $fitment->capacities()) }} Ач</dd>
                        @endif
                        @if ($fitment->polarity)
                            <dt>Полярность</dt>
                            <dd>{{ $fitment->polarity }}</dd>
                        @endif
                        @if ($fitment->dimensions() !== [])
                            <dt>Габариты (Д×Ш×В, мм)</dt>
                            <dd>{{ collect($fitment->dimensions())->map(fn (array $dims) => implode('×', $dims))->implode(', ') }}</dd>
                        @endif
                    </dl>
                    <a href="{{ route('home') }}#battery-wizard" class="btn btn-default btn-block">Выбрать другой автомобиль</a>
                </div>
            </aside>
            <div id="content" class="col-sm-8 col-md-9 col-lg-9 col-xxl-16">
                <div class="content-top">
                    @include('home.banners')
                </div>
                <div class="uni-wrapper">
                    @if ($products->isEmpty())
                        <div class="div-text-empty">
                            Подходящих аккумуляторов сейчас нет в наличии. Позвоните нам —
                            <a href="tel:+{{ preg_replace('/\D/', '', config('shop.phone')) }}">{{ config('shop.phone') }}</a>, подберём под заказ.
                        </div>
                    @else
                        @include('partials.catalog-sorts')

                        <div class="products-block row row-flex">
                            @foreach ($products as $product)
                                <div class="product-layout product-grid grid-view col-sm-6 col-md-4 col-lg-4 col-xxl-5">
                                    @include('partials.product-card', ['product' => $product])
                                </div>
                            @endforeach
                        </div>

                        {{ $products->links() }}
                        <div class="pagination-text">Показано с {{ $products->firstItem() }} по {{ $products->lastItem() }} из {{ $products->total() }} (всего {{ $products->lastPage() }} <x-plural :count="$products->lastPage()" forms="страница|страницы|страниц" />)</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
