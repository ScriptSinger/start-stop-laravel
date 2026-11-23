{{-- Разметка — 1:1 с header.twig/menu.twig темы UniShop2 старого сайта
     (классы и вложенность важны: на них завязан CSS темы). --}}
@php($phoneHref = 'tel:+'.preg_replace('/\D/', '', config('shop.phone')))
@php($logoUrl = Illuminate\Support\Facades\Storage::disk('public')->url(config('shop.logo')))
@php($hasWishlist = Route::has('wishlist.index'))
@php($hasCompare = Route::has('compare.index'))

<header data-saved-counts data-wishlist="{{ $wishlistCount ?? 0 }}" data-compare="{{ $compareCount ?? 0 }}" data-wishlist-ids="{{ json_encode($wishlistIds ?? []) }}" data-compare-ids="{{ json_encode($compareIds ?? []) }}">
    <div id="top" class="top-menu">
        <div class="container">
            <div class="top-menu__links">
                <div class="top-links btn-group" x-data="{open: false}" :class="{open: open}" @click.outside="open = false" @keydown.escape="open = false">
                    <button class="top-menu__btn dropdown-toggle" aria-label="Меню" aria-haspopup="true" :aria-expanded="open.toString()" @click="open = !open"><i class="fas fa-bars"></i></button>
                    <ul class="top-links__ul dropdown-menu dropdown-menu-left">
                        @foreach ($topLinks as $link)
                            <li class="top-links__li"><a class="top-links__a" href="{{ $link->href() }}" title="{{ $link->title }}">{{ $link->title }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <div class="top-menu__buttons">
                @if ($hasWishlist)
                    <div class="top-menu__wishlist status-2">
                        <div class="btn-group">
                            <button class="top-menu__btn top-menu__wishlist-btn uni-href" data-href="{{ route('wishlist.index') }}"><i class="far fa-heart"></i><span class="top-menu__btn-text">Закладки</span><span class="top-menu__wishlist-total uni-badge" x-text="$store.saved.wishlist">{{ $wishlistCount ?? 0 }}</span></button>
                        </div>
                    </div>
                @endif
                @if ($hasCompare)
                    <div class="top-menu__compare status-2">
                        <div class="btn-group">
                            <button class="top-menu__btn top-menu__compare-btn uni-href" data-href="{{ route('compare.index') }}"><i class="top-menu__compare-icon fas fa-align-right"></i><span class="top-menu__btn-text">Сравнение</span><span class="top-menu__compare-total uni-badge" x-text="$store.saved.compare">{{ $compareCount ?? 0 }}</span></button>
                        </div>
                    </div>
                @endif
                {{-- Личный кабинет — как #account в шапке темы UniShop2. --}}
                <div class="top-menu__account status-2">
                    <div id="account" class="btn-group" x-data="{open: false}" :class="{open: open}" @click.outside="open = false" @keydown.escape="open = false">
                        @guest
                            <a class="top-menu__btn top-menu__account-link" href="{{ route('login') }}" aria-label="Войти" @click.prevent="$store.modal.open($el.href, 'Авторизация')"><i class="far fa-user" aria-hidden="true"></i><span class="top-menu__btn-text">Войти</span></a>
                        @else
                            <button class="top-menu__btn dropdown-toggle" aria-label="Личный кабинет" aria-haspopup="true" :aria-expanded="open.toString()" @click="open = !open"><i class="far fa-user" aria-hidden="true"></i><span class="top-menu__btn-text">Личный кабинет</span></button>
                            <ul class="dropdown-menu dropdown-menu-right">
                                <li><a href="{{ route('account') }}"><i class="fas fa-fw fa-user"></i>Личный кабинет</a></li>
                                <li><a href="{{ route('account.orders') }}"><i class="fas fa-fw fa-clipboard-list"></i>История заказов</a></li>
                                <li><form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="top-menu__logout"><i class="fas fa-fw fa-sign-out-alt"></i>Выход</button></form></li>
                            </ul>
                        @endguest
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="header-block">
            <div class="header-block__item header-block__item-logo col-sm-6 col-md-3 col-lg-3 col-xxl-4">
                <div id="logo" class="header-logo">
                    @if (request()->routeIs('home'))
                        <img src="{{ $logoUrl }}" title="{{ config('shop.name') }}" alt="{{ config('shop.name') }}" width="1080" height="442" decoding="async" class="header-logo__img img-responsive" />
                    @else
                        <a href="{{ route('home') }}"><img src="{{ $logoUrl }}" title="{{ config('shop.name') }}" alt="{{ config('shop.name') }}" width="1080" height="442" decoding="async" class="header-logo__img img-responsive" /></a>
                    @endif
                </div>
            </div>

            <div id="search" class="header-block__item header-block__item-search header-search-row hidden-xs hidden-sm">
                @include('partials.search-form')
                <a class="btn btn-primary header-pick" href="{{ route('car-landing.index') }}"><i class="fas fa-car-battery" aria-hidden="true"></i>Подбор по авто</a>
            </div>

            <div class="header-block__item header-block__item-telephone">
                <div class="header-phones has-addit" x-data="{open: false}" :class="{open: open}" @click.outside="open = false" @keydown.escape="open = false">
                    <a class="header-phones__main" href="{{ $phoneHref }}" title="">{{ config('shop.phone') }}</a>
                    <i class="header-phones__show-phone dropdown-toggle fas fa-chevron-down" role="button" tabindex="0" aria-label="Ещё способы связи" aria-haspopup="true" :aria-expanded="open.toString()" @click="open = !open" @keydown.enter="open = !open"></i>
                    <ul class="header-phones__ul dropdown-menu dropdown-menu-right">
                        <li class="header-phones__li">
                            <a href="{{ route('callback.create') }}" class="header-phones__callback" @click.prevent="$store.modal.open($el.href, 'Заказать звонок')">Заказать звонок</a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="header-block__item header-block__item-account">
                @guest
                    <a class="header-account header-action" href="{{ route('login') }}" title="Войти" aria-label="Войти" @click.prevent="$store.modal.open($el.href, 'Авторизация')"><i class="header-account__icon far fa-user" aria-hidden="true"></i><span class="header-action__label">Войти</span></a>
                @else
                    <a class="header-account header-action" href="{{ route('account') }}" title="Личный кабинет" aria-label="Личный кабинет"><i class="header-account__icon far fa-user" aria-hidden="true"></i><span class="header-action__label">Личный кабинет</span></a>
                @endguest
            </div>
            @if ($hasWishlist)
                <div class="header-block__item header-block__item-wishlist">
                    <a class="header-wishlist header-action" href="{{ route('wishlist.index') }}" aria-label="Избранное"><i class="header-wishlist__icon far fa-heart" aria-hidden="true"></i><span class="header-wishlist__total-items" x-text="$store.saved.wishlist" x-show="$store.saved.wishlist > 0">{{ $wishlistCount ?? 0 }}</span><span class="header-action__label">Избранное</span></a>
                </div>
            @endif
            @if ($hasCompare)
                <div class="header-block__item header-block__item-compare">
                    <a class="header-compare header-action" href="{{ route('compare.index') }}" aria-label="Сравнение"><i class="header-compare__icon site-icon--compare fas fa-align-right" aria-hidden="true"></i><span class="header-compare__total-items" x-text="$store.saved.compare" x-show="$store.saved.compare > 0">{{ $compareCount ?? 0 }}</span><span class="header-action__label">Сравнение</span></a>
                </div>
            @endif

            <div class="header-block__item header-block__item-cart">
                <div id="cart" class="header-cart" title="Корзина">
                    {{-- Без JavaScript — ссылка на корзину, с ним — окно с мини-корзиной. --}}
                    <a class="header-cart__btn header-action dropdown-toggle" href="{{ route('cart.index') }}" aria-label="Корзина" @click.prevent="$store.cart.open()">
                        <i class="header-cart__icon fa fa-shopping-cart" aria-hidden="true"></i>
                        <span id="cart-total" class="header-cart__total-items" x-text="$store.cart.count" x-show="$store.cart.count > 0">{{ $cartCount }}</span>
                        <span class="header-action__label">Корзина</span>
                    </a>
                    <div class="header-cart__dropdown" data-mini-cart-html data-state-url="{{ route('cart.state') }}" data-customer="{{ auth('web')->check() ? '1' : '0' }}" data-count="{{ $cartCount }}" data-products="{{ $cartLines->map(fn ($line) => $line->product->id)->values()->toJson() }}" x-html="$store.cart.html">
                        @include('partials.mini-cart')
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <div class="main-menu set-before">
                <div class="menu-wrapper col-md-3 col-lg-3 col-xxl-4">
                    <nav id="menu" class="menu menu1">
                        <div class="menu__header">
                            <i class="menu__header-icon fa fa-fw fa-bars hidden-xs hidden-sm"></i>
                            <span class="menu__header-title">Каталог</span>
                            <i class="menu-close menu__header-icon fas fa-times visible-xs visible-sm"></i>
                        </div>
                        <ul class="menu__collapse main-menu__collapse">
                            @include('partials.category-menu-items')
                        </ul>
                    </nav>
                </div>
                <div class="menu-open visible-xs visible-sm">
                    <i class="menu-open__icon fas fa-bars"></i>
                    <span class="menu-open__title show-on-mobile">Каталог</span>
                </div>
                <div class="col-xs-12 col-md-9 col-lg-9 col-xxl-16 hidden-xs hidden-sm">
                    <nav id="menu2" class="menu menu2 menu-right">
                        <ul class="menu__collapse">
                            @foreach ($mainMenu as $item)
                                @if ($item->href() === route('contact'))
                            <li class="menu__level-1-li {{ url()->current() === route('business') ? 'is-active' : '' }}">
                                <a class="menu__level-1-a" href="{{ route('business') }}" @if (url()->current() === route('business')) aria-current="page" @endif><i class="menu__level-1-icon fas fa-briefcase fa-fw" aria-hidden="true"></i>Для организаций</a>
                            </li>
                                @endif
                                @php($isCurrent = $item->href() !== null && url()->current() === strtok($item->href(), '?'))
                                <li class="menu__level-1-li {{ $item->children->isNotEmpty() ? 'has-children' : '' }} {{ $isCurrent ? 'is-active' : '' }}">
                                    <a class="menu__level-1-a" @if ($item->href()) href="{{ $item->href() }}" @endif @if ($isCurrent) aria-current="page" @endif>@if ($item->icon)<i class="menu__level-1-icon {{ $item->icon }} fa-fw"></i> @endif{{ $item->title }}</a>
                                    @if ($item->children->isNotEmpty())
                                        <span class="menu__pm menu__level-1-pm visible-xs visible-sm"><i class="fa fa-plus"></i><i class="fa fa-minus"></i></span>
                                        <div class="menu__level-2 column-1">
                                            <ul class="menu__level-2-ul col-md-12">
                                                @foreach ($item->children as $child)
                                                    <li class="menu__level-2-li"><a class="menu__level-2-a" href="{{ $child->href() }}">{{ $child->title }}</a></li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                            @if (! $mainMenu->contains(fn ($item) => $item->href() === route('contact')))
                            <li class="menu__level-1-li {{ url()->current() === route('business') ? 'is-active' : '' }}">
                                <a class="menu__level-1-a" href="{{ route('business') }}" @if (url()->current() === route('business')) aria-current="page" @endif><i class="menu__level-1-icon fas fa-briefcase fa-fw" aria-hidden="true"></i>Для организаций</a>
                            </li>
                            @endif
                        </ul>
                    </nav>
                </div>
            </div>
            <div id="search2" class="visible-xs visible-sm">@include('partials.search-form')<a class="btn btn-primary header-pick header-pick--mobile" href="{{ route('car-landing.index') }}"><i class="fas fa-car-battery" aria-hidden="true"></i>Подбор по авто</a></div>
            <div class="business-mobile-nav visible-xs visible-sm">
                <details class="business-mobile-nav__menu">
                    <summary><span>Меню</span><i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
                    <nav aria-label="Разделы сайта">
                        @auth
                            <a href="{{ route('account.orders') }}"><i class="fas fa-clipboard-list" aria-hidden="true"></i>История заказов</a>
                        @endauth
                        <span class="business-mobile-nav__heading">Разделы сайта</span>
                        @foreach ($mainMenu as $item)
                            @if ($item->href() === route('contact'))
                                <a href="{{ route('business') }}" @class(['is-active' => url()->current() === route('business')])>Для организаций</a>
                            @endif
                            @if ($item->href())
                                <a href="{{ $item->href() }}" @class(['is-active' => url()->current() === strtok($item->href(), '?')])>{{ $item->title }}</a>
                            @else
                                <span class="business-mobile-nav__heading">{{ $item->title }}</span>
                            @endif
                            @foreach ($item->children as $child)
                                <a href="{{ $child->href() }}">{{ $child->title }}</a>
                            @endforeach
                        @endforeach
                        @if (! $mainMenu->contains(fn ($item) => $item->href() === route('contact')))
                            <a href="{{ route('business') }}" @class(['is-active' => url()->current() === route('business')])>Для организаций</a>
                        @endif
                        <span class="business-mobile-nav__heading">Информация</span>
                        @foreach ($topLinks as $link)
                            <a href="{{ $link->href() }}">{{ $link->title }}</a>
                        @endforeach
                    </nav>
                </details>
            </div>
        </div>
    </div>
</header>

@sectionMissing('no_fly_menu')
    @include('partials.fly-menu')
@endif
