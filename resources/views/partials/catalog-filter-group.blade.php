@include('partials.catalog-filter-group-head', ['key' => $key, 'title' => $title])
    <div class="catalog-filter__values">
        @foreach ($options as $option)
            @php($isChecked = in_array($option['id'], $selected, true))
            {{-- Значение, с которым при текущем фильтре ничего не найдётся, не даём отметить. --}}
            @php($isDisabled = ! $isChecked && $option['count'] === 0)
            <div class="checkbox {{ $isDisabled ? 'disabled text-muted' : '' }}">
                <label>
                    <input type="checkbox" name="{{ $inputName }}" value="{{ $option['id'] }}" data-label="{{ $option['label'] }}" @checked($isChecked) @disabled($isDisabled)>
                    {{ $option['label'] }} <span class="text-muted">({{ $option['count'] }})</span>
                </label>
            </div>
        @endforeach
    </div>
@include('partials.catalog-filter-group-foot')
