<nav class="mobile-navigation" aria-label="Основная навигация">
    <button type="button" class="mobile-navigation__item" aria-label="Открыть каталог" onclick="document.querySelector('header .menu-open')?.click()">
        <i class="fas fa-bars" aria-hidden="true"></i><span>Каталог</span>
    </button>
    @if (Route::has('wishlist.index'))
        <a class="mobile-navigation__item {{ request()->routeIs('wishlist.*') ? 'is-active' : '' }}" href="{{ route('wishlist.index') }}" @if (request()->routeIs('wishlist.*')) aria-current="page" @endif>
            <span class="mobile-navigation__icon"><i class="far fa-heart" aria-hidden="true"></i><span class="mobile-navigation__count" x-show="$store.saved.wishlist > 0" x-cloak x-text="$store.saved.wishlist"></span></span><span>Избранное</span>
        </a>
    @endif
    <a class="mobile-navigation__item {{ request()->routeIs('compare.*') ? 'is-active' : '' }}" href="{{ route('compare.index') }}" @if (request()->routeIs('compare.*')) aria-current="page" @endif>
        <span class="mobile-navigation__icon"><i class="fas fa-align-right site-icon--compare" aria-hidden="true"></i><span class="mobile-navigation__count" x-show="$store.saved.compare > 0" x-cloak x-text="$store.saved.compare"></span></span><span>Сравнение</span>
    </a>
    <a class="mobile-navigation__item {{ request()->routeIs('cart.*', 'checkout.*') ? 'is-active' : '' }}" href="{{ route('cart.index') }}" @if (request()->routeIs('cart.*', 'checkout.*')) aria-current="page" @endif>
        <span class="mobile-navigation__icon"><i class="fa fa-shopping-cart" aria-hidden="true"></i><span class="mobile-navigation__count" x-show="$store.cart.count > 0" x-cloak x-text="$store.cart.count"></span></span><span>Корзина</span>
    </a>
    @guest
        <a class="mobile-navigation__item" href="{{ route('login') }}" @click.prevent="$store.modal.open($el.href, 'Авторизация')"><i class="far fa-user" aria-hidden="true"></i><span>Войти</span></a>
    @else
        <a class="mobile-navigation__item {{ request()->routeIs('account*') ? 'is-active' : '' }}" href="{{ route('account') }}" aria-label="Личный кабинет" @if (request()->routeIs('account*')) aria-current="page" @endif><i class="far fa-user" aria-hidden="true"></i><span>Кабинет</span></a>
    @endguest
</nav>
