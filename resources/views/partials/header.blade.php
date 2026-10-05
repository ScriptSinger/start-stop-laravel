{{-- Верхняя тонкая полоска — реальные страницы (oc_information, bottom=1) --}}
@if (($topPages ?? collect())->isNotEmpty())
    <div class="top-menu">
        <div class="container">
            <ul class="list-inline" style="margin: 0;">
                @foreach ($topPages as $topPage)
                    <li><a href="{{ route('page.show', $topPage) }}" style="color: var(--top-menu-btn-c, #fff);">{{ $topPage->title }}</a></li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<header class="container" style="padding: 15px 0;">
    <div class="row" style="align-items: center;">
        <div class="col-sm-3">
            <a href="{{ route('home') }}" style="font-size: 22px; font-weight: 700; text-decoration: none;">
                {{ config('shop.name') }}
            </a>
        </div>

        <div class="col-sm-4">
            <form action="{{ route('home') }}" method="GET" class="form-inline" style="display: flex;">
                <input
                    type="text"
                    name="search"
                    value="{{ $search ?? '' }}"
                    class="form-control"
                    style="flex: 1;"
                    placeholder="Поиск по каталогу"
                />
                <button type="submit" class="btn btn-default"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <div class="col-sm-2">
            <a href="tel:{{ preg_replace('/\D/', '', config('shop.phone')) }}" style="font-weight: 600; text-decoration: none;">
                {{ config('shop.phone') }}
            </a>
        </div>

        {{-- Аккаунт/вишлист/сравнение — визуально как в оригинале, но без бэкенда:
        это сознательно не перенесённые фичи (см. план, Фаза 3). Иконки на месте,
        чтобы шапка выглядела как надо, функциональности за ними нет. --}}
        <div class="col-sm-3 text-right" style="display: flex; justify-content: flex-end; gap: 15px; align-items: center;">
            <i class="far fa-user" title="Аккаунт"></i>
            <span style="position: relative;">
                <i class="far fa-heart" title="Избранное"></i>
                <span class="badge" style="position: absolute; top: -10px; right: -10px;">0</span>
            </span>
            <span style="position: relative;">
                <i class="fas fa-align-right" title="Сравнение"></i>
                <span class="badge" style="position: absolute; top: -10px; right: -10px;">0</span>
            </span>
            <a href="{{ route('cart.index') }}" style="position: relative; color: inherit;" title="Корзина">
                <i class="fas fa-shopping-cart"></i>
                <span class="badge" style="position: absolute; top: -10px; right: -10px;">{{ $cartCount ?? 0 }}</span>
            </a>
        </div>
    </div>

    <nav style="border-top: 1px solid #eee; margin-top: 10px; display: flex; align-items: center;">
        <a href="#category-module" style="background: var(--btn-primary-bg, #c82a00); color: #fff; padding: 10px 20px; text-decoration: none; white-space: nowrap;">
            <i class="fas fa-bars"></i> Категории
        </a>
        <ul class="list-inline" style="margin: 0 0 0 20px;">
            @foreach ($navPages ?? [] as $navPage)
                <li><a href="{{ route('page.show', $navPage) }}">{{ $navPage->title }}</a></li>
            @endforeach
        </ul>
    </nav>
</header>
