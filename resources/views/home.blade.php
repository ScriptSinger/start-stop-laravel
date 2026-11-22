@extends('layouts.app')

@section('body_class', 'menu-expanded')

@push('page-styles')
    @vite('resources/css/storefront/pages/home.css')
@endpush

{{-- Первый баннер — самая крупная картинка экрана: грузим его раньше стилей и скриптов. --}}
@if ($sliderBanners->isNotEmpty())
    @push('page-meta')
        <link rel="preload" as="image" href="{{ $sliderBanners->first()->imageUrl() }}" fetchpriority="high">
    @endpush
@endif

{{-- Сетка — как common/home.twig темы UniShop2: слева пустая колонка (её
     занимает раскрытое меню категорий из шапки), справа баннеры (content-top),
     ниже на всю ширину — блоки главной (content-bottom). --}}
@section('content')
    <div class="home-page container">
        <div class="row">
            <aside id="column-left" class="col-sm-4 col-md-3 col-lg-3 col-xxl-4 hidden-xs hidden-sm"></aside>
            <div id="content" class="col-sm-12 col-md-9 col-lg-9 col-xxl-16">
                <div class="content-top">
                    {{-- Единственный h1 главной: внешний вид не меняем, поэтому скрыт визуально. --}}
                    <h1 class="sr-only">{{ config('shop.name') }} — автомобильные аккумуляторы в Уфе</h1>
                    @include('home.banners')
                </div>
            </div>
        </div>
        <div class="content-bottom">
            @include('partials.battery-filter')
            @include('home.category-wall')
            @foreach (App\Enums\ProductSelection::cases() as $selection)
                @include('home.product-selection', ['selection' => $selection, 'products' => $selections[$selection->value]])
            @endforeach
            @include('home.advantages')
            @include('home.reviews')
            @include('home.about')
            @include('home.contacts')
        </div>
    </div>
@endsection
