{{-- Фильтр каталога: внутри блока значения через «ИЛИ», между блоками — «И».
     Число рядом со значением — сколько товаров будет с учётом остальных условий. --}}
@php($selectedManufacturers = $filter->manufacturerIds())
@php($selectedValues = $filter->attributeValueIds())

<form method="get" action="{{ route('category.show', $category) }}" class="catalog-filter" id="catalog-filter" style="margin-top: 20px;">
    <div class="heading">Фильтр</div>

    <div class="catalog-filter__group form-group">
        <div class="checkbox">
            <label>
                <input type="checkbox" name="available" value="1" @checked($filter->onlyAvailable())>
                Есть в наличии или под заказ
            </label>
        </div>
    </div>

    <div class="catalog-filter__group form-group">
        <strong>Цена, р.</strong>
        <div class="row" style="margin-top: 5px;">
            <div class="col-xs-6">
                <input type="number" min="0" name="price_from" class="form-control input-sm"
                       value="{{ $filter->priceFrom() !== null ? (int) $filter->priceFrom() : '' }}"
                       placeholder="от {{ (int) ($priceBounds->min_price ?? 0) }}">
            </div>
            <div class="col-xs-6">
                <input type="number" min="0" name="price_to" class="form-control input-sm"
                       value="{{ $filter->priceTo() !== null ? (int) $filter->priceTo() : '' }}"
                       placeholder="до {{ (int) ($priceBounds->max_price ?? 0) }}">
            </div>
        </div>
    </div>

    @if ($manufacturerFacet->isNotEmpty())
        @include('partials.catalog-filter-group', [
            'title' => 'Производитель',
            'inputName' => 'manufacturer[]',
            'options' => $manufacturerFacet->map(fn ($manufacturer) => [
                'id' => $manufacturer->id,
                'label' => $manufacturer->name,
                'count' => $manufacturer->count,
            ]),
            'selected' => $selectedManufacturers,
        ])
    @endif

    @foreach ($attributeFacets as $attribute)
        @include('partials.catalog-filter-group', [
            'title' => $attribute->name,
            'inputName' => "attr[{$attribute->id}][]",
            'options' => $attribute->values->map(fn ($value) => [
                'id' => $value->id,
                'label' => $value->value,
                'count' => $value->count,
            ]),
            'selected' => $selectedValues[$attribute->id] ?? [],
        ])
    @endforeach

    <div class="catalog-filter__buttons">
        <button type="submit" class="btn btn-primary">Показать</button>
        @if ($filter->isActive())
            <a href="{{ route('category.show', $category) }}" class="btn btn-default">Сбросить</a>
        @endif
    </div>
</form>

@push('scripts')
    <script>
        // Отметили значение — сразу применяем фильтр (цену — по кнопке «Показать»).
        $('#catalog-filter').on('change', 'input[type=checkbox]', function () {
            this.form.submit();
        });
    </script>
@endpush
