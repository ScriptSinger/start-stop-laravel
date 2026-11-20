@props(['values'])

{{-- Значения характеристики через запятую; значение с подсказкой — ссылкой на окно. --}}
@foreach ($values as $value)
    @if (filled($value->hint))
        <a href="{{ route('hint.value', $value) }}" class="spec-hint-term" @click.prevent="$store.modal.open($el.href, @js($value->value))">{{ $value->value }}</a>@else{{ $value->value }}@endif{{ $loop->last ? '' : ', ' }}
@endforeach
