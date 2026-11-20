@props(['attribute'])

{{-- Название характеристики; со значком «i» и окном подсказки, если подсказка задана. --}}
{{ $attribute->name }}
@if (filled($attribute->hint))
    <a href="{{ route('hint.attribute', $attribute) }}" class="spec-hint-trigger" title="Что это такое" aria-label="Что такое «{{ $attribute->name }}»" @click.prevent="$store.modal.open($el.href, @js($attribute->name))"><i class="fas fa-info-circle" aria-hidden="true"></i></a>
@endif
