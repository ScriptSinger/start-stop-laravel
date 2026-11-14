@extends('layouts.app')

@push('page-styles')
    @vite(['resources/css/storefront/pages/business.css', 'resources/css/storefront/pages/services.css'])
@endpush

@section('content')
    <div class="container business-page services-page">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li>Услуги</li>
            </ul>
            <h1>Услуги для аккумуляторов и автомобиля</h1>
        </div>
        <p class="services-page__lead">Проверим аккумулятор и автомобиль, поможем с зарядкой, обслуживанием и установкой. Выберите услугу или позвоните — подскажем, с чего начать.</p>
        <div class="services-page__cards">
            @foreach (config('shop.service_offers') as $offer)
                <section class="business-page__card services-page__card" id="{{ $offer['id'] }}" aria-labelledby="{{ $offer['id'] }}-title">
                    <i class="fas {{ $offer['icon'] }}" aria-hidden="true"></i>
                    <h2 id="{{ $offer['id'] }}-title">{{ $offer['title'] }}</h2>
                    <p>{{ $offer['description'] }}</p>
                    <div class="services-page__terms">{{ $offer['terms'] }}</div>
                    @if ($offer['id'] === 'maintenance')
                        <a class="services-page__details" href="{{ route('page.show', 'service') }}">Подробнее об обслуживании <i class="fas fa-angle-right" aria-hidden="true"></i></a>
                    @endif
                </section>
            @endforeach
        </div>
        <section class="business-page__contact" aria-labelledby="services-contact-title">
            <div>
                <h2 id="services-contact-title">Поможем выбрать услугу</h2>
                <p>Расскажите, что случилось с аккумулятором или автомобилем. Уточним условия и согласуем время обращения.</p>
                <p>{{ config('shop.address') }}</p>
            </div>
            <div class="business-page__contact-actions">
                <a class="btn btn-primary business-page__button" href="tel:+{{ preg_replace('/\D/', '', config('shop.phone')) }}"><i class="fas fa-phone" aria-hidden="true"></i> {{ config('shop.phone') }}</a>
                <a href="{{ route('callback.create') }}" @click.prevent="$store.modal.open($el.href, 'Заказать звонок')">Заказать обратный звонок</a>
            </div>
        </section>
    </div>
@endsection
