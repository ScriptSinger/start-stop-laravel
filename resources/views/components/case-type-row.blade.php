@props(['code', 'layout' => 'short'])

{{-- Строка «Тип корпуса» (код из поля товара: L3, Груз A) в списке характеристик карточки. --}}
@if ($layout === 'tab')
    <div class="product-data__item">
        <div class="product-data__item-div">Тип корпуса</div>
        <div class="product-data__item-div">{{ $code }}</div>
    </div>
@else
    <div class="product-data__item"><div class="product-data__item-div">Тип корпуса</div>{{ $code }}</div>
@endif
