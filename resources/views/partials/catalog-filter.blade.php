{{-- Фильтр каталога (как OCFilter старого сайта): внутри блока значения через
     «ИЛИ», между блоками — «И». Число рядом со значением — сколько товаров будет
     с учётом остальных условий. Применяется кнопкой «Показать N товаров»;
     на телефоне форма живёт в выезжающей панели. Компонент catalogFilter,
     его x-data — на колонке. --}}
<button type="button" class="catalog-filter-tab" @click="show()"><span>Фильтр</span><i class="fas fa-sliders-h"></i></button>
<div class="catalog-filter-backdrop" x-show="open" x-cloak @click="close()"></div>

<div class="catalog-filter-panel" :class="{open}">
    <form method="get" action="{{ route('category.show', $category) }}" class="catalog-filter" id="catalog-filter" x-ref="form"
          :class="{'has-group': group}" @change="onChange($event)" @submit.prevent="apply()">
        <div class="heading hidden-xs"><i class="fas fa-sliders-h"></i> Фильтр</div>
        <div class="catalog-filter__head">
            <button type="button" class="catalog-filter__back" x-show="group" x-cloak @click="group = null"><i class="fas fa-arrow-left"></i> <span x-text="groupTitle"></span></button>
            <span class="catalog-filter__title" x-show="!group"><i class="fas fa-sliders-h"></i> Фильтр</span>
            <button type="button" class="catalog-filter__close" @click="close()" aria-label="Закрыть">&times;</button>
        </div>

        @include('partials.catalog-filter-group-head', ['key' => 'available', 'title' => 'Наличие', 'desktopTitle' => false])
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="available" value="1" data-label="Есть в наличии или под заказ" @checked($filter->onlyAvailable)>
                    Есть в наличии или под заказ
                </label>
            </div>
        @include('partials.catalog-filter-group-foot')

        @include('partials.catalog-filter-group-head', ['key' => 'price', 'title' => 'Цена, р.'])
            <div class="input-group input-group-sm catalog-filter__price">
                <input type="number" min="0" name="price_from" class="form-control" aria-label="Цена от"
                       value="{{ $filter->priceFrom !== null ? (int) $filter->priceFrom : '' }}" placeholder="{{ $priceMin }}"
                       :value="priceFrom" @change="typePrice('from', $event.target.value)">
                <span class="input-group-addon">-</span>
                <input type="number" min="0" name="price_to" class="form-control" aria-label="Цена до"
                       value="{{ $filter->priceTo !== null ? (int) $filter->priceTo : '' }}" placeholder="{{ $priceMax }}"
                       :value="priceTo" @change="typePrice('to', $event.target.value)">
                <span class="input-group-addon">р.</span>
            </div>
            {{-- Шкала с двумя ползунками (noUiSlider в OCFilter): обычные range без
                 name — в адрес идут только поля выше. --}}
            <div class="price-scale" x-show="hasPriceScale" x-cloak>
                <div class="price-scale__track"><div class="price-scale__range" :style="priceRangeStyle"></div></div>
                <input type="range" class="price-scale__thumb" :min="priceMin" :max="priceMax" step="100" :value="priceFrom" @input="slidePrice('from', $event.target.value)" aria-label="Цена от">
                <input type="range" class="price-scale__thumb" :min="priceMin" :max="priceMax" step="100" :value="priceTo" @input="slidePrice('to', $event.target.value)" aria-label="Цена до">
                <div class="price-scale__ticks"><template x-for="tick in priceTicks"><span x-text="tick"></span></template></div>
            </div>
        @include('partials.catalog-filter-group-foot')

        @if ($manufacturerFacet->isNotEmpty())
            @include('partials.catalog-filter-group', [
                'key' => 'manufacturer',
                'title' => 'Производитель',
                'inputName' => 'manufacturer[]',
                'options' => $manufacturerFacet->map(fn ($manufacturer) => [
                    'id' => $manufacturer->id,
                    'label' => $manufacturer->name,
                    'count' => $manufacturer->count,
                ]),
                'selected' => $filter->manufacturerIds,
            ])
        @endif

        @foreach ($attributeFacets as $attribute)
            @include('partials.catalog-filter-group', [
                'key' => 'attr-'.$attribute->id,
                'title' => $attribute->name,
                'inputName' => "attr[{$attribute->id}][]",
                'options' => $attribute->values->map(fn ($value) => [
                    'id' => $value->id,
                    'label' => $value->value,
                    'count' => $value->count,
                ]),
                'selected' => $filter->selectedValueIds($attribute->id),
            ])
        @endforeach

        {{-- Кнопки прилипают к низу экрана, пока фильтр на виду. --}}
        <div class="catalog-filter__actions">
            <a href="{{ route('category.show', $category) }}" class="catalog-filter__reset {{ $filter->isActive() ? 'is-active' : '' }}" :class="{'is-active': changed}">Сбросить</a>
            <button type="submit" class="btn btn-primary catalog-filter__submit" :disabled="!changed || count === null" x-text="submitLabel">Показать</button>
        </div>

        {{-- Подсказка у только что отмеченного значения (компьютер). --}}
        <div class="catalog-filter__popover" x-show="changed && count !== null && popoverTop !== null" x-cloak :style="`top: ${popoverTop}px`">
            <button type="submit" class="btn btn-primary" x-text="submitLabel"></button>
        </div>
    </form>
</div>
