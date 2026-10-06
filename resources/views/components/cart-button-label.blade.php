@props(['product', 'icon' => 'fa-shopping-bag', 'text' => 'В корзину'])
{{-- Иконка и подпись кнопки «В корзину»: для товара в корзине — «В корзине»
     с галочкой (uniChangeBtn темы). --}}
@php($inCart = '$store.cart.has('.$product->id.')')
<i class="fa {{ $icon }}" :class="{'{{ $icon }}': !{{ $inCart }}, 'fa-check': {{ $inCart }}}"></i><span x-text="{{ $inCart }} ? 'В корзине' : @js($text)">{{ $text }}</span>
