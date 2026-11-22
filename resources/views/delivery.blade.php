@extends('layouts.app')

@push('page-styles')
    @vite(['resources/css/storefront/pages/business.css', 'resources/css/storefront/pages/delivery.css'])
@endpush

@php
    $phoneHref = 'tel:+'.preg_replace('/\D/', '', config('shop.phone'));
    $zones = collect(config('shop.delivery_zones'));
    $freeZones = $zones->filter(fn (int $price): bool => $price === 0)->keys();
    $paidZones = $zones->filter(fn (int $price): bool => $price > 0);
@endphp

@section('content')
    <div class="container business-page delivery-page">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li>Доставка и оплата</li>
            </ul>
            <h1>Доставка аккумуляторов в Уфе</h1>
        </div>
        <p class="delivery-page__lead">Привезём заказ по Уфе курьером или заберёте сами в магазине — сотрудники зарезервируют нужный товар. В большинстве районов города доставка бесплатная.</p>

        <section class="business-page__section" aria-labelledby="delivery-ways-title">
            <div class="business-page__section-heading">
                <p class="business-page__eyebrow">Как получить заказ</p>
                <h2 id="delivery-ways-title">Способы получения</h2>
            </div>
            <div class="delivery-page__cards">
                <div class="business-page__card">
                    <i class="fas fa-store" aria-hidden="true"></i>
                    <h3>Самовывоз из магазина</h3>
                    <p>Нужен аккумулятор срочно? Заберите заказ сами — мы зарезервируем товар.</p>
                </div>
                <div class="business-page__card">
                    <i class="fas fa-shipping-fast" aria-hidden="true"></i>
                    <h3>Курьером по Уфе</h3>
                    <p>Привезём по адресу. Стоимость зависит от района — смотрите ниже.</p>
                </div>
                <div class="business-page__card">
                    <i class="fas fa-route" aria-hidden="true"></i>
                    <h3>За пределы Уфы</h3>
                    <p>Стоимость обсуждаем индивидуально — позвоните, подскажем.</p>
                </div>
            </div>
        </section>

        <section class="business-page__section" aria-labelledby="delivery-zones-title">
            <div class="business-page__section-heading">
                <p class="business-page__eyebrow">Курьерская доставка</p>
                <h2 id="delivery-zones-title">Стоимость по районам</h2>
            </div>
            <div class="delivery-page__zones">
                <div class="delivery-page__zone-group">
                    <h3>Бесплатно</h3>
                    <ul>
                        @foreach ($freeZones as $zone)
                            <li>{{ $zone }}</li>
                        @endforeach
                    </ul>
                </div>
                @foreach ($paidZones->unique()->values() as $price)
                    <div class="delivery-page__zone-group">
                        <h3>{{ number_format($price, 0, ',', ' ') }} ₽</h3>
                        <ul>
                            @foreach ($paidZones->filter(fn (int $zonePrice): bool => $zonePrice === $price)->keys() as $zone)
                                <li>{{ $zone }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
            <p class="delivery-page__note">Доставка в отдалённые районы — по договорённости.</p>
        </section>

        <section class="business-page__section" aria-labelledby="delivery-pay-title">
            <div class="business-page__section-heading">
                <p class="business-page__eyebrow">Оплата</p>
                <h2 id="delivery-pay-title">Как оплатить</h2>
            </div>
            <div class="delivery-page__cards delivery-page__cards--two">
                <div class="business-page__card">
                    <i class="fas fa-credit-card" aria-hidden="true"></i>
                    <h3>В магазине</h3>
                    <p>Наличными, по QR-коду или банковской картой.</p>
                </div>
                <div class="business-page__card">
                    <i class="fas fa-qrcode" aria-hidden="true"></i>
                    <h3>Курьеру при получении</h3>
                    <p>Наличными или по QR-коду.</p>
                </div>
            </div>
            <p class="delivery-page__note">Цены в розничных магазинах могут отличаться от указанных на сайте.</p>
        </section>

        <section class="business-page__details" aria-labelledby="delivery-legal-title">
            <div>
                <p class="business-page__eyebrow">Реквизиты</p>
                <h2 id="delivery-legal-title">Продавец</h2>
            </div>
            <p class="delivery-page__legal"><span><b>Наименование:</b> ИП Валитов А. Г.</span><span><b>ИНН:</b> 0233 0213 5549</span></p>
        </section>

        <section class="business-page__contact" aria-labelledby="delivery-contact-title">
            <div>
                <h2 id="delivery-contact-title">Остались вопросы?</h2>
                <p>Уточним стоимость доставки для вашего адреса и согласуем удобное время.</p>
            </div>
            <div class="business-page__contact-actions">
                <a class="btn btn-primary business-page__button" href="{{ $phoneHref }}"><i class="fas fa-phone" aria-hidden="true"></i> {{ config('shop.phone') }}</a>
                <a href="{{ route('callback.create') }}" @click.prevent="$store.modal.open($el.href, 'Заказать звонок')">Заказать обратный звонок</a>
            </div>
        </section>
    </div>
@endsection
