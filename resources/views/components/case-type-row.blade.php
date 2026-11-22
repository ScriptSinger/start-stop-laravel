@props(['code', 'layout' => 'short'])

{{-- Строка «Тип корпуса» (код из поля товара: L3, Груз A) в списке характеристик карточки, со значком «i» и окном подсказки. --}}
@php
    $trigger = '<a href="'.e(route('hint.case-type')).'" rel="nofollow" class="spec-hint-trigger" title="Что это такое" aria-label="Что такое «Тип корпуса»" @click.prevent="$store.modal.open($el.href, \'Тип корпуса\')"><i class="fas fa-info-circle" aria-hidden="true"></i></a>';
@endphp
@if ($layout === 'tab')
    <div class="product-data__item">
        <div class="product-data__item-div">Тип корпуса {!! $trigger !!}</div>
        <div class="product-data__item-div">{{ $code }}</div>
    </div>
@else
    <div class="product-data__item"><div class="product-data__item-div">Тип корпуса {!! $trigger !!}</div>{{ $code }}</div>
@endif
