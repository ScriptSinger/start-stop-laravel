{{-- Меню кабинета справа — как column_right старого сайта (модуль account). --}}
<aside id="column-right" class="col-sm-4 col-md-3 col-lg-3 col-xxl-4 hidden-xs">
    <div class="list-group">
        @guest
            <a href="{{ route('login') }}" class="list-group-item">Вход</a>
            <a href="{{ route('register') }}" class="list-group-item">Регистрация</a>
            <a href="{{ route('password.request') }}" class="list-group-item">Забыли пароль?</a>
        @else
            <a href="{{ route('account') }}" class="list-group-item">Личный кабинет</a>
            <a href="{{ route('account.orders') }}" class="list-group-item">История заказов</a>
            <a href="{{ route('wishlist.index') }}" class="list-group-item">Закладки</a>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="list-group-item">Выход</button>
            </form>
        @endguest
    </div>
</aside>
