@extends('layouts.app')

@section('robots', 'noindex, nofollow')

@push('page-styles')
    @vite('resources/css/storefront/pages/account.css')
@endpush

{{-- Разметка — как account/account.twig темы UniShop2 старого сайта (плитки разделов). --}}
@section('content')
    <div id="account-account" class="container">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li>Личный кабинет</li>
            </ul>
            <h1>Личный кабинет</h1>
        </div>
        <div class="row">
            <div id="content" class="col-sm-8 col-md-9 col-lg-9 col-xxl-16">
                <div class="uni-wrapper">
                    <p>Здравствуйте, {{ $customer->name }}! Вы вошли как {{ $customer->email }}.</p>
                    <div class="row row-flex">
                        <div class="col-xs-6 col-sm-4 col-md-3">
                            <a href="{{ route('account.orders') }}" class="account-index__item uni-item-bg"><i class="account-index__icon fas fa-clipboard-list"></i><span>История заказов</span></a>
                        </div>
                        <div class="col-xs-6 col-sm-4 col-md-3">
                            <a href="{{ route('wishlist.index') }}" class="account-index__item uni-item-bg"><i class="account-index__icon far fa-heart"></i><span>Закладки</span></a>
                        </div>
                        <div class="col-xs-6 col-sm-4 col-md-3">
                            <form method="post" action="{{ route('logout') }}" class="account-index__item uni-item-bg">
                                @csrf
                                <button type="submit" class="account-index__logout"><i class="account-index__icon fas fa-sign-out-alt"></i><span>Выход</span></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @include('partials.account-menu')
        </div>
    </div>
@endsection
