{{-- Разметка — 1:1 с header.twig/menu.twig темы UniShop2 старого сайта
     (классы и вложенность важны: на них завязан CSS темы). --}}
@php($phoneHref = 'tel:+'.preg_replace('/\D/', '', config('shop.phone')))
@php($logoUrl = Illuminate\Support\Facades\Storage::disk('public')->url(config('shop.logo')))
@php($hasWishlist = Route::has('wishlist.index'))
@php($hasCompare = Route::has('compare.index'))

<header>
    <div id="top" class="top-menu">
        <div class="container">
            <div class="top-menu__links">
                <div class="top-links btn-group">
                    <button class="top-menu__btn dropdown-toggle" aria-label="Меню" data-toggle="dropdown"><i class="fas fa-bars"></i></button>
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
                            <button class="top-menu__btn top-menu__wishlist-btn uni-href" data-href="{{ route('wishlist.index') }}"><i class="far fa-heart"></i><span class="top-menu__btn-text">Закладки</span><span class="top-menu__wishlist-total uni-badge">{{ $wishlistCount ?? 0 }}</span></button>
                        </div>
                    </div>
                @endif
                @if ($hasCompare)
                    <div class="top-menu__compare status-2">
                        <div class="btn-group">
                            <button class="top-menu__btn top-menu__compare-btn uni-href" data-href="{{ route('compare.index') }}"><i class="top-menu__compare-icon fas fa-align-right"></i><span class="top-menu__btn-text">Сравнение</span><span class="top-menu__compare-total uni-badge">{{ $compareCount ?? 0 }}</span></button>
                        </div>
                    </div>
                @endif
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

            <div id="search" class="header-block__item header-block__item-search hidden-xs hidden-sm">
                <form class="header-search" action="{{ route('search') }}" method="get">
                    <div class="header-search__form">
                        <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Поиск" aria-label="Поиск" autocomplete="off" class="header-search__input form-control" />
                        <button type="button" class="search-btn-clear {{ filled($search ?? null) ? 'show' : '' }}" aria-label="Очистить">&times;</button>
                        <button type="submit" class="header-search__btn search-btn" title="Поиск"><i class="fa fa-search"></i></button>
                    </div>
                </form>
            </div>

            <div class="header-block__item header-block__item-telephone">
                <div class="header-phones has-addit">
                    <a class="header-phones__main" href="{{ $phoneHref }}" title="">{{ config('shop.phone') }}</a>
                    <i class="header-phones__show-phone dropdown-toggle fas fa-chevron-down" data-toggle="dropdown" data-target="header-phones__ul"></i>
                    <ul class="header-phones__ul dropdown-menu dropdown-menu-right">
                        <li class="header-phones__li">
                            <a href="{{ route('callback.create') }}" class="header-phones__callback" data-modal-url="{{ route('callback.create') }}" data-modal-title="Заказать звонок">Заказать звонок</a>
                        </li>
                    </ul>
                </div>
            </div>

            @if ($hasWishlist)
                <div class="header-block__item header-block__item-wishlist">
                    <div class="header-wishlist uni-href" data-href="{{ route('wishlist.index') }}" title="Закладки"><i class="header-wishlist__icon far fa-heart"></i><span class="header-wishlist__total-items">{{ $wishlistCount ?? 0 }}</span></div>
                </div>
            @endif
            @if ($hasCompare)
                <div class="header-block__item header-block__item-compare">
                    <div class="header-compare uni-href" data-href="{{ route('compare.index') }}" title="Сравнение"><i class="header-compare__icon fas fa-align-right"></i><span class="header-compare__total-items">{{ $compareCount ?? 0 }}</span></div>
                </div>
            @endif

            <div class="header-block__item header-block__item-cart">
                <div id="cart" class="header-cart" title="Корзина">
                    {{-- Без JavaScript — ссылка на корзину, с ним — окно с мини-корзиной. --}}
                    <a class="header-cart__btn dropdown-toggle" href="{{ route('cart.index') }}" aria-label="Корзина" data-mini-cart-open>
                        <i class="header-cart__icon fa fa-shopping-cart"></i>
                        <span id="cart-total" class="header-cart__total-items">{{ $cartCount }}</span>
                    </a>
                    <div class="header-cart__dropdown">
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
                            <span class="menu__header-title">Категории</span>
                            <i class="menu-close menu__header-icon fas fa-times visible-xs visible-sm"></i>
                        </div>
                        <ul class="menu__collapse main-menu__collapse">
                            @foreach ($categoryMenu as ['category' => $menuCategory, 'children' => $children])
                                <li class="menu__level-1-li {{ $children !== [] ? 'has-children' : '' }}">
                                    <a class="menu__level-1-a" href="{{ route('category.show', $menuCategory) }}">
                                        @if ($menuCategory->hasFontIcon())
                                            <i class="menu__level-1-icon {{ $menuCategory->icon }} fa-fw"></i>
                                        @elseif ($menuCategory->icon)
                                            <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($menuCategory->icon) }}" alt="{{ $menuCategory->name }}" class="menu__level-1-img" loading="lazy" />
                                        @endif
                                        {{ $menuCategory->name }}
                                    </a>
                                    @if ($children !== [])
                                        <span class="menu__pm menu__level-1-pm visible-xs visible-sm"><i class="fa fa-plus"></i><i class="fa fa-minus"></i></span>
                                        <div class="menu__level-2 {{ count($children) > 12 ? 'column-3' : 'column-1' }}">
                                            @foreach ($children as $child)
                                                <div class="menu__level-2-ul {{ count($children) > 12 ? 'col-md-4' : 'col-md-12' }}">
                                                    <a class="menu__level-2-a" href="{{ $child['url'] }}">{{ $child['title'] }}</a>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                </div>
                <div class="menu-open visible-xs visible-sm">
                    <i class="menu-open__icon fas fa-bars"></i>
                    <span class="menu-open__title show-on-mobile">Категории</span>
                </div>
                <div class="col-xs-12 col-md-9 col-lg-9 col-xxl-16 hidden-xs hidden-sm">
                    <nav id="menu2" class="menu menu2 menu-right">
                        <ul class="menu__collapse">
                            @foreach ($mainMenu as $item)
                                <li class="menu__level-1-li {{ $item->children->isNotEmpty() ? 'has-children' : '' }}">
                                    <a class="menu__level-1-a" @if ($item->href()) href="{{ $item->href() }}" @endif>@if ($item->icon)<i class="menu__level-1-icon {{ $item->icon }} fa-fw"></i> @endif{{ $item->title }}</a>
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
                        </ul>
                    </nav>
                </div>
            </div>
            <div id="search2" class="visible-xs visible-sm"></div>
        </div>
    </div>
</header>
