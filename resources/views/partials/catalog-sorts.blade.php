{{-- Строка сортировки и «товаров на странице» (sorts-block темы UniShop2). --}}
{{-- Пока покупатель не выбрал сортировку, показываем «По умолчанию» (в наличии первыми, затем по цене). --}}
@php($chosenSort = App\Enums\CatalogSort::tryFrom((string) request('sort')))
@php($sort = $chosenSort === null ? App\Enums\CatalogSort::Default : $chosenSort)
@php($sortUrl = fn (App\Enums\CatalogSort $option) => request()->fullUrlWithQuery(['sort' => $option->value, 'page' => null]))
@php($toggle = fn (App\Enums\CatalogSort $asc, App\Enums\CatalogSort $desc) => $sort === $asc ? $desc : $asc)

<div class="sorts-block">
    <div class="sorts-block__wrapper">
        <div class="sorts-block__sorts hidden-xs hidden-sm">
            <span data-href="{{ $sortUrl(App\Enums\CatalogSort::Default) }}" class="sorts-block__span uni-href {{ $sort === App\Enums\CatalogSort::Default ? 'selected' : '' }}">По умолчанию</span>
            <span data-href="{{ $sortUrl($toggle(App\Enums\CatalogSort::NameAsc, App\Enums\CatalogSort::NameDesc)) }}" class="sorts-block__span uni-href {{ $sort->isByName() ? 'selected '.($sort->isDescending() ? 'up' : 'down') : '' }}">Название </span>
            <span data-href="{{ $sortUrl($toggle(App\Enums\CatalogSort::PriceAsc, App\Enums\CatalogSort::PriceDesc)) }}" class="sorts-block__span uni-href {{ $sort->isByPrice() ? 'selected '.($sort->isDescending() ? 'up' : 'down') : '' }}">Цена </span>
        </div>
        {{-- Сортировка на телефоне и планшете: своё меню под кнопкой вместо системного списка
             (в эмуляции телефона он рисуется не у кнопки). Пункты — обычные ссылки. --}}
        <div class="sort-menu visible-xs visible-sm" x-data="{open: false}" @click.outside="open = false" @keydown.escape="open = false">
            <button type="button" class="sort-menu__button" aria-haspopup="true" :aria-expanded="open.toString()" aria-label="Сортировка" @click="open = !open">
                <i class="fas fa-sort-amount-down" aria-hidden="true"></i>
                <span class="sort-menu__current">{{ $sort->label() }}</span>
                <i class="fas fa-chevron-down sort-menu__chevron" :class="{'is-open': open}" aria-hidden="true"></i>
            </button>
            <div class="sort-menu__panel" x-show="open" x-cloak>
                @foreach ([
                    [null, [App\Enums\CatalogSort::Default]],
                    ['Название', [App\Enums\CatalogSort::NameAsc, App\Enums\CatalogSort::NameDesc]],
                    ['Цена', [App\Enums\CatalogSort::PriceAsc, App\Enums\CatalogSort::PriceDesc]],
                ] as [$group, $options])
                    @if ($group)
                        <span class="sort-menu__group">{{ $group }}</span>
                    @endif
                    @foreach ($options as $option)
                        <a href="{{ $sortUrl($option) }}" @class(['sort-menu__item', 'is-selected' => $sort === $option]) @if ($sort === $option) aria-current="true" @endif>{{ $option->label() }}@if ($sort === $option)<i class="fas fa-check" aria-hidden="true"></i>@endif</a>
                    @endforeach
                @endforeach
            </div>
        </div>
        <select class="sorts-block__select sorts-block__limit form-control hidden-xs" @change="location = $event.target.value" aria-label="Товаров на странице">
            @foreach (App\Http\Requests\CatalogFilterRequest::PER_PAGE_OPTIONS as $option)
                <option value="{{ request()->fullUrlWithQuery(['limit' => $option, 'page' => null]) }}" @selected($perPage === $option)>{{ $option }}</option>
            @endforeach
        </select>
    </div>
</div>
