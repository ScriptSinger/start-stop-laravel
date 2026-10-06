@extends('layouts.app')

{{-- Разметка — 1:1 с information/contact.twig темы UniShop2. Форму «Написать
     нам» не переносим: без уведомлений сообщения некуда доставить. --}}
@push('page-styles')
    @vite('resources/css/storefront/pages/contact.css')
@endpush

@section('content')
    <div id="contact-page" class="container">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li>Связаться с нами</li>
            </ul>
            <h1>Связаться с нами</h1>
        </div>
        <div class="row">
            <div id="content" class="col-sm-12">
                <div class="uni-wrapper">
                    <div class="contacts">
                        <div class="row">
                            <div class="col-xs-12 col-sm-6">
                                <div class="heading">Контакты</div>
                                <div class="row-flex">
                                    <div class="contacts__address">
                                        <div class="contacts__heading">Адрес</div>
                                        <ul class="contact-list__address list-unstyled">
                                            <li class="contact-list__item">{{ config('shop.address') }}</li>
                                        </ul>
                                    </div>
                                    <div class="contacts__contacts">
                                        <div class="contacts__heading">Контакты</div>
                                        <ul class="contact-list list-unstyled">
                                            <li class="contact-list__item uni-href" data-href="tel:{{ config('shop.phone_alt') }}"><i class="contact-list__icon fa fa-phone-alt fa-fw"></i><span>{{ config('shop.phone_alt') }}</span></li>
                                            <li class="contact-list__item uni-href" data-href="tel:+{{ preg_replace('/\D/', '', config('shop.phone')) }}"><i class="contact-list__icon fas fa-phone fa-fw"></i><span>{{ config('shop.phone') }}</span></li>
                                            <li class="contact-list__item uni-href" data-href="mailto:{{ config('shop.email') }}"><i class="contact-list__icon far fa-envelope fa-fw" aria-hidden="true"></i><span>{{ config('shop.email') }}</span></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="contacts__map col-xs-12 col-sm-6">
                                <div class="heading">Схема проезда</div>
                                <div data-yandex-map="{{ config('shop.yandex_map_src') }}"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
