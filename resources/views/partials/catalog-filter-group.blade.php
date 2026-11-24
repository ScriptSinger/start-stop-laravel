{{-- Значения, с которыми при текущем фильтре ничего не найдётся (0), не показываем:
     так список короче и нет «мёртвых» строк. Отмеченное значение остаётся, чтобы его можно было снять.
     Группа без значений не выводится вовсе. --}}
@php($options = collect($options)->filter(fn ($option) => $option['count'] > 0 || in_array($option['id'], $selected, true))->values())
@if ($options->isNotEmpty())
    @include('partials.catalog-filter-group-head', ['key' => $key, 'title' => $title])
        <div class="catalog-filter__values">
            @foreach ($options as $option)
                @php($isChecked = in_array($option['id'], $selected, true))
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="{{ $inputName }}" value="{{ $option['id'] }}" data-label="{{ $option['label'] }}" @checked($isChecked)>
                        {{ $option['label'] }} <span class="text-muted">({{ $option['count'] }})</span>
                    </label>
                </div>
            @endforeach
        </div>
    @include('partials.catalog-filter-group-foot')
@endif
