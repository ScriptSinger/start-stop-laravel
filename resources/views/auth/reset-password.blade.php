@extends('layouts.app')

@section('robots', 'noindex, nofollow')

@push('page-styles')
    @vite('resources/css/storefront/pages/account.css')
@endpush

@section('content')
    <div id="account-reset" class="container">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li>Новый пароль</li>
            </ul>
            <h1>Новый пароль</h1>
        </div>
        <div class="row">
            <div id="content" class="col-sm-8 col-md-9 col-lg-9 col-xxl-16">
                <div class="uni-wrapper">
                    <div class="account-forgotten uni-form">
                        <form method="post" action="{{ route('password.update') }}">
                            @csrf
                            <input type="hidden" name="token" value="{{ $request->route('token') }}" />
                            @foreach ([
                                ['email', 'E-Mail адрес', 'email', old('email', $request->email), 'email'],
                                ['password', 'Новый пароль', 'password', null, 'new-password'],
                                ['password_confirmation', 'Повторите пароль', 'password', null, 'new-password'],
                            ] as [$field, $label, $type, $value, $autocomplete])
                                <div @class(['form-group', 'has-error' => $errors->has($field)])>
                                    <input type="{{ $type }}" name="{{ $field }}" value="{{ $value }}" placeholder="{{ $label }}" aria-label="{{ $label }}" class="form-control" autocomplete="{{ $autocomplete }}" required />
                                    @error($field)<span class="help-block">{{ $message }}</span>@enderror
                                </div>
                            @endforeach
                            @error('token')<div class="help-block text-danger">{{ $message }}</div>@enderror
                            <button type="submit" class="account-forgotten__btn btn btn-primary">Сохранить пароль</button>
                        </form>
                    </div>
                </div>
            </div>
            @include('partials.account-menu')
        </div>
    </div>
@endsection
