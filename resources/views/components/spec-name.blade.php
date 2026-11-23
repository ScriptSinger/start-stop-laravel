@props(['attribute'])

{{-- Название характеристики; со значком «i» и окном подсказки, если подсказка задана.
     Значок держится при последнем слове, чтобы при переносе названия не оказаться на своей строке. --}}
@php
    $words = preg_split('/\s+/u', trim($attribute->name), -1, PREG_SPLIT_NO_EMPTY) ?: [(string) $attribute->name];
    $lastWord = array_pop($words);
@endphp
<span class="spec-name">{{ implode(' ', $words) }}@if ($words !== []) @endif<span class="spec-name__tail">{{ $lastWord }}
@if (filled($attribute->hint))
    <a href="{{ route('hint.attribute', $attribute) }}" rel="nofollow" class="spec-hint-trigger" title="Что это такое" aria-label="Что такое «{{ $attribute->name }}»" @click.prevent="$store.modal.open($el.href, @js($attribute->name))"><i class="fas fa-info-circle" aria-hidden="true"></i></a>
@endif</span></span>
