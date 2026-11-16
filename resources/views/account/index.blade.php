@extends('layouts.app')

@section('body_class', 'menu-expanded')

@section('robots', 'noindex, nofollow')

@push('page-styles')
    @vite('resources/css/storefront/pages/account.css')
@endpush

@section('content')
    <div id="account-account" class="container account-layout">
        <div class="breadcrumb-h1 col-md-offset-3 col-lg-offset-3 col-xxl-offset-4">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li>Личный кабинет</li>
            </ul>
            <h1>Личный кабинет</h1>
        </div>
        <div class="row">
            <aside id="column-left" class="col-sm-4 col-md-3 col-lg-3 col-xxl-4 hidden-xs hidden-sm"></aside>
            <div id="content" class="col-sm-12 col-md-9 col-lg-9 col-xxl-16">
                <div class="uni-wrapper account-content">
                    <section class="account-index__profile" aria-labelledby="account-profile-title">
                        <h2 id="account-profile-title">Здравствуйте, {{ $customer->name }}!</h2>
                        @if ($customer->email ?? $customer->yandex_email ?? $customer->vk_email)
                            <p class="account-index__email">{{ $customer->email ?? $customer->yandex_email ?? $customer->vk_email }}</p>
                        @endif
                        @if ($customer->email && $customer->hasVerifiedEmail())
                            <p class="account-index__verified"><i class="fas fa-check-circle" aria-hidden="true"></i> Почта подтверждена</p>
                        @endif
                    </section>
                    @if ($customer->email && ! $customer->hasVerifiedEmail())
                        <div class="account-index__email-verification">
                            <p class="account-index__email-verification-title">Подтвердите адрес электронной почты</p>
                            <p>Почта не подтверждена. Проверьте письмо со ссылкой или отправьте его ещё раз.</p>
                            <form method="post" action="{{ route('verification.send') }}">
                                @csrf
                                <button type="submit" class="btn account-index__button account-index__button--primary">Отправить письмо ещё раз</button>
                            </form>
                        </div>
                    @endif
                    @if (session('status') === 'verification-link-sent')
                        <div class="alert alert-success" role="status">Письмо для подтверждения отправлено. Проверьте почту и папку «Спам».</div>
                    @elseif (session('status'))
                        <div class="alert alert-success" role="status">{{ session('status') }}</div>
                    @endif
                    @error('yandex')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror
                    @error('vkid')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror
                    <section class="account-index__purchases" aria-labelledby="account-purchases-title">
                        <h2 id="account-purchases-title">Мои покупки</h2>
                        <div class="account-index__purchase-links">
                            <a href="{{ route('account.orders') }}"><i class="fas fa-clipboard-list" aria-hidden="true"></i><span>История заказов</span><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                            <a href="{{ route('wishlist.index') }}"><i class="far fa-heart" aria-hidden="true"></i><span>Избранное</span><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                        </div>
                    </section>
                    @if ((filled(config('services.yandex.client_id')) && filled(config('services.yandex.client_secret')) && filled(config('services.yandex.redirect'))) || (filled(config('services.vkid.client_id')) && filled(config('services.vkid.redirect'))))
                        <section class="account-index__connections" aria-labelledby="account-connections-title">
                            <h2 id="account-connections-title">Способы входа</h2>
                            <p>Привяжите аккаунт, чтобы входить в магазин без пароля.</p>
                            <div class="account-index__connection-actions">
                                @if (filled(config('services.yandex.client_id')) && filled(config('services.yandex.client_secret')) && filled(config('services.yandex.redirect')))
                                    @if ($customer->yandex_id)
                                        <p class="account-index__connection-status"><i class="fas fa-check-circle" aria-hidden="true"></i> Яндекс ID привязан</p>
                                    @else
                                        <form class="account-index__connection" method="post" action="{{ route('auth.yandex.link') }}">
                                            @csrf
                                            <button type="submit" class="btn account-index__button">Привязать Яндекс ID</button>
                                        </form>
                                    @endif
                                @endif
                                @if (filled(config('services.vkid.client_id')) && filled(config('services.vkid.redirect')))
                                    @if ($customer->vk_id)
                                        <p class="account-index__connection-status"><i class="fas fa-check-circle" aria-hidden="true"></i> VK ID привязан</p>
                                    @else
                                        <form class="account-index__connection" method="post" action="{{ route('auth.vkid.link') }}">
                                            @csrf
                                            <button type="submit" class="btn account-index__button">Привязать VK ID</button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </section>
                    @endif
                    @if ($customer->email === null && (($customer->yandex_id && $customer->yandex_email) || ($customer->vk_id && $customer->vk_email)))
                        <section class="account-index__merge uni-form" aria-labelledby="legacy-orders-title">
                            <h2 id="legacy-orders-title" class="account-index__merge-title">Заказы из прежнего аккаунта</h2>
                            <p>У вас уже был аккаунт магазина? Укажите его пароль, чтобы увидеть прежние заказы. Покупать можно и без этого шага.</p>
                            <form method="post" action="{{ $customer->yandex_id && $customer->yandex_email ? route('auth.yandex.merge') : route('auth.vkid.merge') }}" class="account-index__merge-form">
                                @csrf
                                <div @class(['form-group', 'has-error' => $errors->has('password')])>
                                    <label for="legacy-account-password">Пароль от прежнего аккаунта</label>
                                    <input id="legacy-account-password" type="password" name="password" autocomplete="current-password" required class="form-control" />
                                    @error('password')<span class="help-block">{{ $message }}</span>@enderror
                                </div>
                                <button type="submit" class="btn btn-primary">Показать прежние заказы</button>
                            </form>
                        </section>
                    @endif
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="account-index__logout"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Выйти из аккаунта</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
