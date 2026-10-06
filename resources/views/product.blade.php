@extends('layouts.app')

@push('page-styles')
    @vite('resources/css/storefront/pages/product.css')
@endpush

{{-- Разметка — 1:1 с product/product.twig темы UniShop2 старого сайта. --}}
@php($category = $product->categories->first())
@php($image = Illuminate\Support\Facades\Storage::disk('public')->url($product->image ?: 'no_image.png'))
@php($heading = $product->heading ?: $product->name)
@php([$availabilityText, $availabilityClass] = match (true) {
    $product->quantity > 0 => ['В наличии', 't-5'],
    $product->isAvailableOnOrder() => ['Под заказ', 't-2'],
    default => ['Нет в наличии — уточним срок по телефону', 't-1'],
})

@section('content')
    <div class="container">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                @if ($category)
                    <li><a href="{{ route('category.show', $category) }}">{{ $category->name }}</a></li>
                    @if ($product->manufacturer)
                        <li><a href="{{ route('category.show', ['category' => $category, 'manufacturer' => [$product->manufacturer->id]]) }}">{{ $product->manufacturer->name }}</a></li>
                    @endif
                @endif
            </ul>
            <h1>{{ $heading }}</h1>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="row">
            <div id="content" class="col-sm-12">
                <div id="product" class="uni-wrapper">
                    <div class="row">
                        <div class="product-page col-sm-12 col-md-12 col-lg-10">
                            <div class="row">
                                <div class="product-page__image col-sm-6" x-data="{image: @js($image)}">
                                    <div class="product-page__image-main">
                                        @if ($product->hasSpecial() || $product->hasTradeIn() || $product->is_pickup_only)
                                            <div class="sticker">
                                                @if ($product->hasSpecial())
                                                    <div class="sticker__item special">Ваша скидка: {{ number_format($product->specialDiscount(), 0, '', '') }}р.</div>
                                                @endif
                                                @if ($product->hasTradeIn())
                                                    <div class="sticker__item ean">Трейд-ин {{ number_format((float) $product->trade_in_discount, 0, '', '') }} руб.</div>
                                                @endif
                                                @if ($product->is_pickup_only)
                                                    <div class="sticker__item jan">Только самовывоз</div>
                                                @endif
                                            </div>
                                        @endif
                                        <div class="product-page__image-main-carousel">
                                            <img src="{{ $image }}" :src="image" alt="{{ $heading }}" title="{{ $heading }}" width="500" height="400" class="product-page__image-main-img img-responsive" />
                                        </div>
                                    </div>
                                    @if ($product->images->isNotEmpty())
                                        <div class="product-page__image-addit">
                                            @foreach ([$product->image, ...$product->images->pluck('path')] as $path)
                                                @if ($path)
                                                    @php($url = Illuminate\Support\Facades\Storage::disk('public')->url($path))
                                                    <img src="{{ $url }}" alt="{{ $heading }}" class="product-page__image-addit-img img-responsive" width="74" height="74" loading="lazy" @click="image = @js($url)" />
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                {{-- livePrice: трейд-ин и количество пересчитывают цену с анимацией, как в теме. --}}
                                <div class="product-block col-sm-6" x-data="livePrice({price: {{ $product->hasSpecial() ? (float) $product->price : $product->displayPrice() }}, special: {{ $product->hasSpecial() ? $product->displayPrice() : 'null' }}, tradeInDiscount: {{ (float) $product->trade_in_discount }}})" @quantity-changed="quantity = $event.detail">
                                    <div class="product-data">
                                        @if ($product->code)
                                            <div class="product-data__item"><div class="product-data__item-div">Код товара:</div> {{ $product->code }}</div>
                                        @endif
                                        @if ($product->manufacturer)
                                            <div class="product-data__item"><div class="product-data__item-div">Производитель:</div> {{ $product->manufacturer->name }}</div>
                                        @endif
                                    </div>

                                    <div class="qty-indicator" data-text="Наличие:">
                                        <div class="qty-indicator__text {{ $availabilityClass }}"> {{ $availabilityText }} </div>
                                    </div>
                                    @if ($product->is_pickup_only)
                                        <div class="text-muted">Только самовывоз</div>
                                    @endif

                                    <div class="product-page__price price">
                                        @if ($product->hasSpecial())
                                            <span class="price-old" x-text="format(shownPrice)">{{ number_format((float) $product->price, 0, '', '') }}р.</span><span class="price-new" x-text="format(shownSpecial)">{{ number_format($product->displayPrice(), 0, '', '') }}р.</span>
                                        @else
                                            <span x-text="format(shownPrice)">{{ number_format($product->displayPrice(), 0, '', '') }}р.</span>
                                        @endif
                                    </div>

                                    <form method="post" action="{{ route('cart.store', $product) }}" id="product-cart-form" @submit.prevent="$store.cart.add($el)">
                                        @csrf
                                        @if ($product->hasTradeIn())
                                            <div class="product-page__option option row">
                                                <div class="option__group col-xs-12">
                                                    <label class="option__group-name">Выберите:</label>
                                                    <div>
                                                        <label class="option__item" data-toggle="tooltip" title="- {{ number_format((float) $product->trade_in_discount, 0, '', '') }}р.">
                                                            <input type="checkbox" name="trade_in" value="1" x-model="tradeIn" />
                                                            <span class="option__name"><i class="fa fa-recycle fa-fw"></i> <span class="option__tit">Трейд-ин</span> <span class="option__val"> {{ number_format($product->priceFor(true), 0, '', '') }}р. </span></span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        <div class="product-page__cart">
                                            <div class="qty-switch" x-data="qtySwitch({max: {{ \App\Services\Cart\Cart::MAX_QUANTITY }}})">
                                                <input type="text" name="quantity" value="1" :value="value" @change="set($event.target.value)" class="qty-switch__input form-control" aria-label="Количество" inputmode="numeric" />
                                                <div>
                                                    <i class="qty-switch__btn fa fa-plus" @click="step(1)"></i>
                                                    <i class="qty-switch__btn fa fa-minus" @click="step(-1)"></i>
                                                </div>
                                            </div>
                                            <button type="submit" class="product-page__add-to-cart add_to_cart btn btn-xl" :class="{in_cart: $store.cart.has({{ $product->id }})}">
                                                @if ($product->isAvailableOnOrder())
                                                    <x-cart-button-label :product="$product" icon="fa-truck" text="Заказать" />
                                                @else
                                                    <x-cart-button-label :product="$product" />
                                                @endif
                                            </button>
                                            <a href="{{ route('quick-order.create', $product) }}" class="product-page__quick-order quick-order btn btn-lg btn-xl" title="Быстрый заказ" aria-label="Быстрый заказ" @click.prevent="$store.modal.open($el.href, 'Быстрый заказ')"><i class="far fa-paper-plane"></i><span>Быстрый заказ</span></a>
                                        </div>
                                        <button type="submit" title="В закладки" class="product-page__wishlist-btn wishlist" formaction="{{ route('wishlist.store', $product) }}" :class="{active: $store.saved.has('wishlist', {{ $product->id }})}" @click.prevent="$store.saved.toggle('wishlist', {{ $product->id }}, $el)"><i class="far fa-heart"></i><span x-text="$store.saved.has('wishlist', {{ $product->id }}) ? 'В закладках' : 'В закладки'">В закладки</span></button>
                                        <button type="submit" title="В сравнение" class="product-page__compare-btn compare" formaction="{{ route('compare.store', $product) }}" :class="{active: $store.saved.has('compare', {{ $product->id }})}" @click.prevent="$store.saved.toggle('compare', {{ $product->id }}, $el)"><i class="fas fa-align-right"></i><span x-text="$store.saved.has('compare', {{ $product->id }}) ? 'В сравнении' : 'В сравнение'">В сравнение</span></button>
                                    </form>

                                    @if (($shortSpecifications = $specifications->take(6))->isNotEmpty())
                                        <div class="product-page__short-attribute product-data">
                                            @foreach ($shortSpecifications as $name => $value)
                                                <div class="product-data__item"><div class="product-data__item-div">{{ $name }}</div>{{ $value }}</div>
                                            @endforeach
                                        </div>
                                        <a class="product-page__more-attr" href="#tab-specification" @click.prevent="$showTab('#tab-specification')">Все характеристики</a>
                                    @endif

                                    <div class="product-page__share">
                                        <div id="goodshare"><div class="vkontakte" data-social="vkontakte"></div><div class="telegram" data-social="telegram"></div></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-12 col-lg-2">
                            <div class="product-banner row row-flex">
                                @foreach (config('shop.product_banners') as $banner)
                                    <div class="col-xs-6 col-sm-4 col-md-4 col-lg-12">
                                        <div class="product-banner__item">
                                            <i class="product-banner__icon {{ $banner['icon'] }} fa-fw"></i>
                                            <div class="product-banner__text">
                                                <span class="product-banner__text-span">{{ $banner['text'] }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="product-page__tabs-gap hidden-xs hidden-sm"></div>
                    <div>
                        <ul class="product-page-tabs nav nav-tabs">
                            @if ($product->hasDescription())
                                <li class="active"><a href="#tab-description" data-toggle="tab">Описание</a></li>
                            @endif
                            @if ($specifications->isNotEmpty())
                                <li class="{{ $product->hasDescription() ? '' : 'active' }}"><a href="#tab-specification" data-toggle="tab">Характеристики</a></li>
                            @endif
                            <li class="{{ $product->hasDescription() || $specifications->isNotEmpty() ? '' : 'active' }}"><a href="#tab-question" class="tab-question" data-toggle="tab">Вопрос-ответ</a></li>
                        </ul>
                        <div class="tab-content">
                            @if ($product->hasDescription())
                                <div class="tab-pane active" id="tab-description">{!! $product->description !!}</div>
                            @endif
                            @if ($specifications->isNotEmpty())
                                <div class="tab-pane {{ $product->hasDescription() ? '' : 'active' }}" id="tab-specification">
                                    <div class="product-data">
                                        @foreach ($specifications as $name => $value)
                                            <div class="product-data__item">
                                                <div class="product-data__item-div">{{ $name }}</div>
                                                <div class="product-data__item-div">{{ $value }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            <div class="tab-pane {{ $product->hasDescription() || $specifications->isNotEmpty() ? '' : 'active' }}" id="tab-question">
                                <div class="question-info">
                                    <p>Есть вопрос о товаре? Напишите — мы перезвоним и ответим.</p>
                                    <a href="{{ route('product-question.create', $product) }}" class="btn btn-sm btn-primary" @click.prevent="$store.modal.open($el.href, 'Задать вопрос')">Задать вопрос</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if ($similarProducts->isNotEmpty())
                    <div class="heading">Похожие товары</div>
                    <div class="uni-module similar-products" data-uni-module="carousel">
                        <div class="uni-module__wrapper">
                            @foreach ($similarProducts as $similarProduct)
                                @include('partials.product-card', ['product' => $similarProduct])
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="content-bottom">
                    @include('home.advantages')
                    @include('home.map')
                </div>
            </div>
        </div>
    </div>
@endsection
