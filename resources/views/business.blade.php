@extends('layouts.app')

@push('page-styles')
    @vite('resources/css/storefront/pages/business.css')
@endpush

@php($phoneHref = 'tel:+'.preg_replace('/\D/', '', config('shop.phone')))

@section('content')
    <div class="container business-page">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li>Для организаций</li>
            </ul>
            <h1>Для организаций</h1>
        </div>

        <div class="business-page__hero">
            <div class="business-page__hero-content">
                <p class="business-page__eyebrow">Аккумуляторы для бизнеса в Уфе</p>
                <h2>Подберём аккумуляторы для вашего транспорта</h2>
                <p class="business-page__lead">Работаем с юридическими лицами. Поможем подобрать АКБ для служебного автомобиля или автопарка, уточним наличие и согласуем условия заказа.</p>
                <div class="business-page__actions">
                    <a class="btn btn-primary business-page__button" href="{{ $phoneHref }}"><i class="fas fa-phone" aria-hidden="true"></i> Позвонить нам</a>
                    <a class="business-page__text-link" href="mailto:{{ config('shop.email') }}">Написать на почту <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                </div>
            </div>
            <div class="business-page__hero-panel" aria-label="Возможности для организаций">
                <div><i class="fas fa-car-battery" aria-hidden="true"></i><span>Подбор по автомобилю и параметрам АКБ</span></div>
                <div><i class="fas fa-clipboard-check" aria-hidden="true"></i><span>Согласование наличия и условий заказа</span></div>
                <div><i class="fas fa-shipping-fast" aria-hidden="true"></i><span>Доставка по Уфе или самовывоз</span></div>
            </div>
        </div>

        <section class="business-page__section" aria-labelledby="business-services-title">
            <div class="business-page__section-heading">
                <p class="business-page__eyebrow">Что мы делаем</p>
                <h2 id="business-services-title">Поможем на каждом этапе</h2>
            </div>
            <div class="business-page__cards">
                <div class="business-page__card">
                    <i class="fas fa-car-battery" aria-hidden="true"></i>
                    <h3>Подберём АКБ</h3>
                    <p>По марке и модели автомобиля или нужным характеристикам аккумулятора.</p>
                </div>
                <div class="business-page__card">
                    <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                    <h3>Уточним наличие</h3>
                    <p>Проверим нужные позиции и количество, предложим подходящие варианты.</p>
                </div>
                <div class="business-page__card">
                    <i class="fas fa-truck" aria-hidden="true"></i>
                    <h3>Согласуем получение</h3>
                    <p>Обсудим доставку по Уфе или самовывоз из магазина.</p>
                </div>
            </div>
        </section>

        <section class="business-page__section business-page__process" aria-labelledby="business-process-title">
            <div class="business-page__section-heading">
                <p class="business-page__eyebrow">Простой порядок работы</p>
                <h2 id="business-process-title">Как сделать заказ</h2>
            </div>
            <ol class="business-page__steps">
                <li><span class="business-page__step-number">01</span><div><h3>Расскажите, что нужно</h3><p>Позвоните или напишите нам. Укажите модели автомобилей, характеристики АКБ и количество, если они известны.</p></div></li>
                <li><span class="business-page__step-number">02</span><div><h3>Получите предложение</h3><p>Уточним наличие, стоимость, способ оплаты и необходимые для вашей организации документы.</p></div></li>
                <li><span class="business-page__step-number">03</span><div><h3>Согласуйте получение</h3><p>Подтвердим детали заказа и договоримся о доставке или самовывозе.</p></div></li>
            </ol>
        </section>

        <section class="business-page__details" aria-labelledby="business-details-title">
            <div>
                <p class="business-page__eyebrow">Условия сотрудничества</p>
                <h2 id="business-details-title">Оплата и документы</h2>
            </div>
            <p>Напишите, какие условия оплаты и документы нужны вашей организации. Обсудим их до подтверждения заказа и ответим на вопросы по получению товара.</p>
        </section>

        <section class="business-page__contact" aria-labelledby="business-contact-title">
            <div>
                <p class="business-page__eyebrow">Свяжитесь с нами</p>
                <h2 id="business-contact-title">Обсудим вашу задачу</h2>
                <p>Расскажите, какие аккумуляторы нужны. Поможем с подбором и условиями заказа.</p>
            </div>
            <div class="business-page__contact-actions">
                <a class="btn btn-primary business-page__button" href="{{ $phoneHref }}">{{ config('shop.phone') }}</a>
                <a href="mailto:{{ config('shop.email') }}">{{ config('shop.email') }}</a>
            </div>
        </section>
    </div>
@endsection
