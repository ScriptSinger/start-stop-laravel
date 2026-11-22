@extends('layouts.app')

{{-- Форму «Написать нам» не переносим: без уведомлений сообщения некуда доставить. --}}
@push('page-styles')
    @vite(['resources/css/storefront/pages/business.css', 'resources/css/storefront/pages/contact.css'])
@endpush

@php($phoneHref = 'tel:+'.preg_replace('/\D/', '', config('shop.phone')))

@section('content')
    <div id="contact-page" class="container business-page contact-page">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li>Связаться с нами</li>
            </ul>
            <h1>Связаться с нами</h1>
        </div>

        <div class="business-page__cards">
            <div class="business-page__card">
                <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                <h3>Адрес</h3>
                <p>{{ config('shop.address') }}</p>
            </div>
            @if (config('shop.opening_hours') !== [])
                <div class="business-page__card">
                    <i class="fas fa-clock" aria-hidden="true"></i>
                    <h3>Режим работы</h3>
                    @foreach (config('shop.opening_hours') as $hours)
                        <p>{{ $hours['days'] }}: {{ $hours['from'] }}–{{ $hours['to'] }}</p>
                    @endforeach
                </div>
            @endif
            <div class="business-page__card">
                <i class="fas fa-phone-alt" aria-hidden="true"></i>
                <h3>Связь</h3>
                <p><a href="{{ $phoneHref }}">{{ config('shop.phone') }}</a></p>
                <p><a href="mailto:{{ config('shop.email') }}">{{ config('shop.email') }}</a></p>
            </div>
        </div>

        <section class="business-page__section" aria-labelledby="contact-map-title">
            <div class="business-page__section-heading">
                <p class="business-page__eyebrow">Как нас найти</p>
                <h2 id="contact-map-title">Схема проезда</h2>
            </div>
            <div class="contact-page__map" data-yandex-map data-coordinates="{{ json_encode(config('shop.map_coordinates')) }}" data-map-key="{{ config('services.yandex_maps.key') }}"></div>
        </section>

        <section class="business-page__contact" aria-labelledby="contact-cta-title">
            <div>
                <h2 id="contact-cta-title">Позвоните — подскажем</h2>
                <p>Поможем подобрать аккумулятор, уточним наличие и условия получения.</p>
            </div>
            <div class="business-page__contact-actions">
                <a class="btn btn-primary business-page__button" href="{{ $phoneHref }}"><i class="fas fa-phone" aria-hidden="true"></i> {{ config('shop.phone') }}</a>
                <a href="{{ route('callback.create') }}" @click.prevent="$store.modal.open($el.href, 'Заказать звонок')">Заказать обратный звонок</a>
            </div>
        </section>
    </div>
@endsection
