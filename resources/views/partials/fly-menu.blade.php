{{-- Шапка, прилипающая при прокрутке (fly-menu темы; на старом сайте её
     собирал скрипт fly-menu-cart.js из копий частей шапки). Настройки
     старого сайта: на компьютере — меню категорий, поиск, телефон и корзина;
     на телефоне сверху — «Главная», меню, поиск и корзина. Значка личного
     кабинета нет: кабинета у нас нет. --}}
<div x-data="flyMenu" @scroll.window.throttle.50ms="onScroll" @keydown.escape.window="close">
<div class="fly-menu-backdrop" x-show="open === 'search'" x-cloak @click="close"></div>
<div id="fly-menu" class="fly-menu" :class="{show: shown}" @click.outside="close">
    <div class="container">
        <div class="row">
            <div class="fly-menu__menu hidden-xs hidden-sm">
                <button class="fly-menu__menu-btn header-menu__btn">
                    <i class="menu__header-icon fa fa-fw fa-bars hidden-xs hidden-sm"></i>
                    <span class="menu__header-title">Категории</span>
                </button>
                <div class="container fly-menu__dropdown">
                    <div class="menu-wrapper new">
                        <nav class="menu menu1 new">
                            <ul class="menu__collapse main-menu__collapse">
                                @include('partials.category-menu-items')
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
            <div class="fly-menu__search hidden-xs hidden-sm">@include('partials.search-form')</div>
            <div class="fly-menu__phone uni-href hidden-xs hidden-sm" data-href="tel:+{{ preg_replace('/\D/', '', config('shop.phone')) }}">{{ config('shop.phone') }}</div>

            <div class="fly-menu__block fly-menu__home uni-href visible-xs visible-sm" data-href="{{ route('home') }}"><i class="fly-menu__icon fly-menu__icon-menu fas fa-home"></i></div>
            <div class="fly-menu__block fly-menu__menu-m visible-xs visible-sm" :class="{show: open === 'menu'}"><i class="fly-menu__icon fly-menu__icon-menu fas fa-bars" @click="toggle('menu')"></i></div>
            <div class="fly-menu__block fly-menu__search-m visible-xs visible-sm" :class="{show: open === 'search'}">
                <i class="fly-menu__icon fly-menu__icon-search fas fa-search" @click="toggle('search')"></i>
                @include('partials.search-form')
            </div>

            <div class="fly-menu__block fly-menu__cart">
                <i class="fly-menu__icon fly-menu__icon-cart fa fa-shopping-bag" @click="$store.cart.open()"></i>
                <span class="fly-menu__cart-total fly-menu__total" x-text="$store.cart.count">{{ $cartCount }}</span>
            </div>
        </div>
    </div>
</div>
</div>
