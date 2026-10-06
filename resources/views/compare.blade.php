@extends('layouts.app')

@push('page-styles')
    @vite('resources/css/storefront/pages/compare.css')
@endpush

{{-- Разметка — 1:1 с product/compare.twig темы UniShop2 старого сайта. --}}
@section('content')
    <div id="product-compare" class="container">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li>Сравнение товаров</li>
            </ul>
            <h1>Сравнение товаров</h1>
        </div>
        <div class="row">
            <div id="content" class="col-sm-12">
                <div class="uni-wrapper">
                    <div class="compare-page-wrapper" x-data="{onlyDifferences: true}">
                        @if ($products->isNotEmpty())
                            <label class="compare-attribute-hide-same input {{ $specificationNames->isNotEmpty() ? 'is_visible' : '' }}"><input type="checkbox" value="1" checked="checked" id="compare-hide-same" x-model="onlyDifferences" /> Показывать только отличия</label>
                            <div class="compare-page {{ $products->count() > 2 ? 'more' : '' }}" data-products="{{ $products->count() }}">
                                <div class="compare-page__row">
                                    @foreach ($products as $product)
                                        <div class="compare-page__info compare-page__cell text-center">
                                            <div class="compare-page__img">
                                                <a href="{{ route('product.show', $product) }}"><img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($product->image ?: 'no_image.png') }}" alt="{{ $product->name }}" title="{{ $product->name }}" class="img-responsive" /></a>
                                                <form method="post" action="{{ route('compare.destroy', $product) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="compare-page__delete" title="Удалить"><i class="far fa-trash-alt"></i></button>
                                                </form>
                                            </div>
                                            <div class="compare-page__name"><a href="{{ route('product.show', $product) }}">{{ $product->name }}</a></div>
                                            <div class="compare-page__price">
                                                <div class="price">
                                                    @if ($product->hasSpecial())
                                                        <span class="price-old">{{ number_format((float) $product->price, 0, '', '') }}р.</span> <span class="price-new">{{ number_format($product->displayPrice(), 0, '', '') }}р.</span>
                                                    @else
                                                        {{ number_format($product->displayPrice(), 0, '', '') }}р.
                                                    @endif
                                                </div>
                                                <form method="post" action="{{ route('cart.store', $product) }}" @submit.prevent="$store.cart.add($el)">
                                                    @csrf
                                                    <button type="submit" class="compare-page__cart add_to_cart button btn" title="В корзину" :class="{in_cart: $store.cart.has({{ $product->id }})}"><x-cart-button-label :product="$product" /></button>
                                                </form>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="compare-page__row">
                                    @foreach ($products as $product)
                                        <div class="compare-page__cell">
                                            <div class="compare-page__cell-heading">Код товара</div>
                                            {{ $product->code ?: '-' }}
                                        </div>
                                    @endforeach
                                </div>
                                <div class="compare-page__row">
                                    @foreach ($products as $product)
                                        <div class="compare-page__cell">
                                            <div class="compare-page__cell-heading">Производитель</div>
                                            {{ $product->manufacturer?->name ?: '-' }}
                                        </div>
                                    @endforeach
                                </div>
                                @php($specifications = $products->mapWithKeys(fn ($product) => [$product->id => $product->specifications()]))
                                @foreach ($specificationNames as $name)
                                    {{-- «Показывать только отличия»: строка, где у всех товаров одно значение, прячется. --}}
                                    @php($isSame = $products->count() > 1 && $products->map(fn ($product) => $specifications[$product->id][$name] ?? '-')->unique()->count() === 1)
                                    <div class="compare-page__row compare-page__attr" @if ($isSame) x-show="!onlyDifferences" @endif>
                                        @foreach ($products as $product)
                                            @php($value = $specifications[$product->id][$name] ?? null)
                                            <div class="compare-page__cell">
                                                <div class="compare-page__cell-heading">{{ $name }}</div>
                                                <span class="compare-page__attr-val">{{ $value ?? '-' }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="compare-page compare-page_empty" data-products="0">
                                <div class="div-text-empty">Вы не выбрали ни одного товара для сравнения.</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
