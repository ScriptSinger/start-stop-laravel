{{-- Строка сортировки и «товаров на странице» (sorts-block темы UniShop2). --}}
@php($sortUrl = fn (App\Enums\CatalogSort $option) => request()->fullUrlWithQuery(['sort' => $option->value, 'page' => null]))
@php($toggle = fn (App\Enums\CatalogSort $asc, App\Enums\CatalogSort $desc) => $sort === $asc ? $desc : $asc)

<div class="sorts-block">
    <div class="sorts-block__wrapper">
        <div class="sorts-block__sorts hidden-xs hidden-sm">
            <span data-href="{{ $sortUrl(App\Enums\CatalogSort::Default) }}" class="sorts-block__span uni-href {{ $sort === App\Enums\CatalogSort::Default ? 'selected' : '' }}">По умолчанию</span>
            <span data-href="{{ $sortUrl($toggle(App\Enums\CatalogSort::NameAsc, App\Enums\CatalogSort::NameDesc)) }}" class="sorts-block__span uni-href {{ $sort->isByName() ? 'selected '.($sort->isDescending() ? 'up' : 'down') : '' }}">Название </span>
            <span data-href="{{ $sortUrl($toggle(App\Enums\CatalogSort::PriceAsc, App\Enums\CatalogSort::PriceDesc)) }}" class="sorts-block__span uni-href {{ $sort->isByPrice() ? 'selected '.($sort->isDescending() ? 'up' : 'down') : '' }}">Цена </span>
        </div>
        <select class="sorts-block__select form-control visible-xs visible-sm" onchange="location = this.value;" aria-label="Сортировка">
            @foreach (App\Enums\CatalogSort::cases() as $option)
                <option value="{{ $sortUrl($option) }}" @selected($sort === $option)>{{ $option->label() }}</option>
            @endforeach
        </select>
        <select class="sorts-block__select sorts-block__limit form-control" onchange="location = this.value;" aria-label="Товаров на странице">
            @foreach (App\Http\Requests\CatalogFilterRequest::PER_PAGE_OPTIONS as $option)
                <option value="{{ request()->fullUrlWithQuery(['limit' => $option, 'page' => null]) }}" @selected($perPage === $option)>{{ $option }}</option>
            @endforeach
        </select>
    </div>
</div>
