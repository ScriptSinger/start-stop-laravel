@extends('layouts.app')

@php($phoneHref = 'tel:+'.preg_replace('/\D/', '', config('shop.phone')))

@section('content')
    <div class="container guide" x-data="guideToc">
        <ul class="breadcrumb">
            <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
            <li>{{ $guide->title }}</li>
        </ul>

        <header class="guide__hero">
            <h1 class="guide__title">{{ $guide->title }}</h1>
            <ul class="guide__meta">
                <li><i class="far fa-clock" aria-hidden="true"></i> {{ $guide->readingMinutes() }} мин чтения</li>
                <li><i class="far fa-calendar-check" aria-hidden="true"></i> Обновлено {{ $guide->updated_at->translatedFormat('j F Y') }}</li>
                <li><i class="fas fa-user-check" aria-hidden="true"></i> Материал магазина «{{ config('shop.name') }}»</li>
            </ul>
        </header>

        <div class="guide__layout">
            <aside class="guide__aside">
                @if ($sections !== [])
                    <nav class="guide__toc" aria-label="Содержание">
                        <div class="guide__toc-title">Содержание</div>
                        <ol class="guide__toc-list">
                            @foreach ($sections as $section)
                                <li><a href="#{{ $section['id'] }}" :class="{'is-active': active === @js($section['id'])}">{{ $section['title'] }}</a></li>
                            @endforeach
                        </ol>
                    </nav>
                @endif
                <div class="guide__help">
                    <div class="guide__help-title">Нужна помощь с выбором?</div>
                    <p>Подскажем и подберём аккумулятор по вашему автомобилю.</p>
                    <a class="guide__help-phone" href="{{ $phoneHref }}"><i class="fas fa-phone-alt" aria-hidden="true"></i> {{ config('shop.phone') }}</a>
                </div>
            </aside>

            <article class="guide__body">
                {!! $body !!}

                <section class="guide-cta" aria-labelledby="guide-cta-title">
                    <div>
                        <h3 class="guide-cta__title" id="guide-cta-title">Не уверены в выборе?</h3>
                        <p class="guide-cta__text">Укажите марку и модель автомобиля, и мы покажем аккумуляторы, которые подходят по размеру, полярности и клеммам. Или позвоните: подскажет специалист магазина.</p>
                    </div>
                    <div class="guide-cta__actions">
                        <a href="{{ route('car-landing.index') }}" class="btn btn-lg btn-primary">Подобрать по автомобилю</a>
                        <a href="{{ route('callback.create') }}" class="btn btn-lg btn-default" @click.prevent="$store.modal.open($el.href, 'Заказать звонок')">Заказать звонок</a>
                    </div>
                </section>
            </article>
        </div>
    </div>
@endsection
