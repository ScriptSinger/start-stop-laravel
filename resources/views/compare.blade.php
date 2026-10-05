@extends('layouts.app')

@section('title', 'Сравнение товаров — '.config('shop.name'))

@push('module-styles')
    <link href="{{ asset('theme/stylesheet/compare.css') }}" rel="stylesheet" media="screen" />
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
                    <div class="compare-page-wrapper">
                        @if ($products->isNotEmpty())
                            <label class="compare-attribute-hide-same input {{ $specificationNames->isNotEmpty() ? 'is_visible' : '' }}"><input type="checkbox" value="1" checked="checked" id="compare-hide-same" /> Показывать только отличия</label>
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
                                                <form method="post" action="{{ route('cart.store', $product) }}">
                                                    @csrf
                                                    <button type="submit" class="compare-page__cart add_to_cart button btn" title="В корзину"><i class="fa fa-shopping-bag"></i><span>В корзину</span></button>
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
                                    <div class="compare-page__row compare-page__attr">
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
                            <div class="compare-page" style="margin:0" data-products="0">
                                <div class="div-text-empty">Вы не выбрали ни одного товара для сравнения.</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // «Показывать только отличия»: прячем строки, где у всех товаров одно значение.
        function compareHideSame() {
            const hide = $('#compare-hide-same').is(':checked');

            $('.compare-page__attr').each(function () {
                const values = $(this).find('.compare-page__attr-val').map((i, el) => $(el).text().trim()).get();

                $(this).toggle(!(hide && values.length > 1 && values.every((value) => value === values[0])));
            });
        }

        $('#compare-hide-same').on('change', compareHideSame);
        compareHideSame();
    </script>
@endpush
