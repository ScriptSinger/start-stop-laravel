@extends('layouts.app')

@section('robots', 'noindex, follow')

@push('page-styles')
    @vite('resources/css/storefront/pages/account.css')
@endpush

{{-- Разметка — как account/register.twig темы UniShop2 старого сайта. --}}
@section('content')
    <div id="account-register" class="container">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li>Регистрация</li>
            </ul>
            <h1>Регистрация</h1>
        </div>
        <div class="row">
            <div id="content" class="col-sm-8 col-md-9 col-lg-9 col-xxl-16">
                <div class="uni-wrapper">
                    <div class="account-register uni-form form-horizontal">
                        <div class="account-register__already">Если вы уже зарегистрированы, перейдите на страницу <a href="{{ route('login') }}">входа в систему</a>. Покупали на сайте раньше? Входите со старым паролем или <a href="{{ route('password.request') }}">восстановите его</a>.</div>
                        <form method="post" action="{{ route('register.store') }}" class="account-register__form">
                            @csrf
                            @foreach ([
                                ['name', 'Имя', 'text', 'given-name', true],
                                ['phone', 'Телефон', 'tel', 'tel', true],
                                ['email', 'E-Mail', 'email', 'email', true],
                                ['password', 'Пароль', 'password', 'new-password', true],
                                ['password_confirmation', 'Подтвердите пароль', 'password', 'new-password', true],
                            ] as [$field, $label, $type, $autocomplete, $required])
                                <div @class(['form-group', 'has-error' => $errors->has($field)])>
                                    <label class="col-sm-3 control-label" for="register-{{ $field }}">{{ $label }}@if ($required) *@endif</label>
                                    <div class="col-sm-9">
                                        <input type="{{ $type }}" name="{{ $field }}" id="register-{{ $field }}" placeholder="{{ $label }}" class="form-control" autocomplete="{{ $autocomplete }}"
                                               @unless ($type === 'password') value="{{ old($field) }}" @endunless @if ($type === 'tel') x-phone-mask @endif @required($required) />
                                        @error($field)<span class="help-block">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                            @endforeach
                            <div @class(['form-group', 'has-error' => $errors->has('agree')])>
                                <div class="col-sm-9 col-sm-offset-3">
                                    <label class="input"><input type="checkbox" name="agree" value="1" @checked(old('agree')) /> <span>Я прочитал и согласен с условиями <a href="{{ route('page.show', config('shop.checkout_agreement_page')) }}" target="_blank"><b>Политика безопасности</b></a></span></label>
                                    @error('agree')<span class="help-block">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="col-sm-9 col-sm-offset-3">
                                    <button type="submit" class="btn btn-lg btn-primary">Продолжить</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @include('partials.account-menu')
        </div>
    </div>
@endsection
