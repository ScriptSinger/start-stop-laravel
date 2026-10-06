@extends('layouts.app')

@section('robots', 'noindex, follow')

@push('page-styles')
    @vite('resources/css/storefront/pages/account.css')
@endpush

{{-- Разметка — как account/login.twig темы UniShop2 старого сайта. --}}
@section('content')
    <div id="account-login" class="container">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li>Авторизация</li>
            </ul>
            <h1>Авторизация</h1>
        </div>
        <div class="row">
            <div id="content" class="col-sm-8 col-md-9 col-lg-9 col-xxl-16">
                <div class="uni-wrapper">
                    @if (session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif
                    <div class="row row-flex">
                        <div class="col-xs-12 col-sm-6">
                            <div class="account-login__wrapper uni-form">
                                <h3 class="account-login__heading uni-form__heading">Постоянный покупатель</h3>
                                @include('auth.partials.login-form')
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="account-login__wrapper uni-form">
                                <h3 class="account-login__heading uni-form__heading">Новый покупатель</h3>
                                <p>Создание учётной записи поможет делать покупки быстрее и удобнее: в личном кабинете видна история ваших заказов и их статусы.</p>
                                <a href="{{ route('register') }}" class="account-login__btn btn btn-lg btn-primary">Продолжить</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @include('partials.account-menu')
        </div>
    </div>
@endsection
