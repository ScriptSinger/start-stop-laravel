<div class="catalog-filter__group form-group">
    <strong>{{ $title }}</strong>
    <div style="max-height: 220px; overflow-y: auto; margin-top: 5px;">
        @foreach ($options as $option)
            @php($isChecked = in_array($option['id'], $selected, true))
            {{-- Значение, с которым при текущем фильтре ничего не найдётся, не даём отметить. --}}
            @php($isDisabled = ! $isChecked && $option['count'] === 0)
            <div class="checkbox {{ $isDisabled ? 'disabled text-muted' : '' }}" style="margin: 2px 0;">
                <label>
                    <input type="checkbox" name="{{ $inputName }}" value="{{ $option['id'] }}" @checked($isChecked) @disabled($isDisabled)>
                    {{ $option['label'] }} <span class="text-muted">({{ $option['count'] }})</span>
                </label>
            </div>
        @endforeach
    </div>
</div>
