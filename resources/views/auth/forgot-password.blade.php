@extends('layouts.app')

@section('robots', 'noindex, follow')

@push('page-styles')
    @vite('resources/css/storefront/pages/account.css')
@endpush

{{-- Разметка — как account/forgotten.twig темы UniShop2 старого сайта. --}}
@section('content')
    <div id="account-forgotten" class="container">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li><a href="{{ route('login') }}">Авторизация</a></li>
                <li>Забыли пароль?</li>
            </ul>
            <h1>Забыли пароль?</h1>
        </div>
        <div class="row">
            <div id="content" class="col-sm-8 col-md-9 col-lg-9 col-xxl-16">
                <div class="uni-wrapper">
                    <div class="account-forgotten uni-form">
                        @if (session('status'))
                            <div class="alert alert-success">{{ session('status') }}</div>
                        @endif
                        <div class="account-forgotten__text">Введите e-mail, с которым вы регистрировались или покупали на сайте. Мы пришлём ссылку, по которой можно задать новый пароль.</div>
                        <form method="post" action="{{ route('password.email') }}">
                            @csrf
                            <div @class(['form-group', 'has-error' => $errors->has('email')])>
                                <input type="email" name="email" value="{{ old('email') }}" placeholder="E-Mail адрес" class="form-control" autocomplete="email" required />
                                @error('email')<span class="help-block">{{ $message }}</span>@enderror
                            </div>
                            <button type="submit" class="account-forgotten__btn btn btn-primary">Продолжить</button>
                        </form>
                    </div>
                </div>
            </div>
            @include('partials.account-menu')
        </div>
    </div>
@endsection
