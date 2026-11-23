@extends('layouts.app')

@push('page-styles')
    @vite('resources/css/storefront/pages/product.css')
@endpush

{{-- Цена в превью ссылки (Open Graph product): у SEOTools для product:* метода нет. --}}
@push('page-meta')
    <meta property="product:price:amount" content="{{ number_format($product->displayPrice(), 2, '.', '') }}">
    <meta property="product:price:currency" content="RUB">
    @if ($product->image)
        {{-- Главное фото — самый крупный элемент страницы: грузим его раньше стилей и скриптов. --}}
        <link rel="preload" as="image" href="{{ App\Services\Catalog\ProductMedia::url($product->image) }}" fetchpriority="high">
    @endif
@endpush

{{-- Разметка — 1:1 с product/product.twig темы UniShop2 старого сайта. --}}
@php($category = $product->categories->first())
@php($image = App\Services\Catalog\ProductMedia::url($product->image ?: 'no_image.png'))
@php($heading = $product->heading ?: $product->name)
@php([$availabilityText, $availabilityClass] = match (true) {
    $product->quantity > 0 => ['В наличии', 't-5'],
    $product->isAvailableOnOrder() => [($onOrderDate = $product->onOrderDate()) ? 'Под заказ, ожидаем '.\App\Services\Supplier\SupplierDelivery::format($onOrderDate) : 'Под заказ', 't-2'],
    default => ['Нет в наличии — уточним срок по телефону', 't-1'],
})

@section('content')
    <div class="container">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                @if ($category)
                    <li><a href="{{ route('category.show', $category) }}">{{ $category->name }}</a></li>
                    @if ($manufacturerUrl)
                        <li><a href="{{ $manufacturerUrl }}">{{ $product->manufacturer->name }}</a></li>
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
                                                    <div class="sticker__item special">Ваша скидка: {{ App\Support\Price::format($product->specialDiscount()) }}</div>
                                                @endif
                                                @if ($product->hasTradeIn())
                                                    <div class="sticker__item ean">Трейд-ин {{ App\Support\Price::format($product->tradeInDiscount()) }}</div>
                                                @endif
                                                @if ($product->is_pickup_only)
                                                    <div class="sticker__item jan">Только самовывоз</div>
                                                @endif
                                            </div>
                                        @endif
                                        <div class="product-page__image-main-carousel">
                                            <button type="button" class="product-image-zoom" aria-label="Увеличить фотографию товара" @click="$refs.photoDialog.showModal()">
                                                <img src="{{ $image }}" :src="image" alt="{{ $heading }}" title="Увеличить фотографию" width="500" height="400" fetchpriority="high" class="product-page__image-main-img img-responsive" />
                                            </button>
                                            <dialog x-ref="photoDialog" class="product-image-dialog" aria-label="Фотография товара" @click.self="$el.close()">
                                                <button type="button" class="product-image-dialog__close" aria-label="Закрыть фотографию" @click="$refs.photoDialog.close()">&times;</button>
                                                <img :src="image" alt="{{ $heading }}" class="product-image-dialog__image" />
                                            </dialog>
                                        </div>
                                    </div>
                                    @if ($product->images->isNotEmpty())
                                        <div class="product-page__image-addit">
                                            @foreach ([$product->image, ...$product->images->pluck('path')] as $path)
                                                @if ($path)
                                                    @php($url = App\Services\Catalog\ProductMedia::url($path))
                                                    <img src="{{ $url }}" alt="{{ $heading }}" class="product-page__image-addit-img img-responsive" width="74" height="74" loading="lazy" @click="image = @js($url)" />
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                {{-- livePrice: трейд-ин и количество пересчитывают цену с анимацией, как в теме. --}}
                                <div class="product-block col-sm-6" x-data="livePrice({price: {{ $product->hasSpecial() ? (float) $product->price : $product->displayPrice() }}, special: {{ $product->hasSpecial() ? $product->displayPrice() : 'null' }}, tradeInDiscount: {{ $product->tradeInDiscount() }}})" @quantity-changed="quantity = $event.detail">
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

                                    <div class="product-page__price price">
                                        @if ($product->hasSpecial())
                                            <span class="price-old" x-text="format(shownPrice)">{{ App\Support\Price::format((float) $product->price) }}</span><span class="price-new" x-text="format(shownSpecial)">{{ App\Support\Price::format($product->displayPrice()) }}</span>
                                        @else
                                            <span x-text="format(shownPrice)">{{ App\Support\Price::format($product->displayPrice()) }}</span>
                                        @endif
                                    </div>

                                    <form method="post" action="{{ route('cart.store', $product) }}" id="product-cart-form" @submit.prevent="$store.cart.add($el)">
                                        @csrf
                                        @if ($product->hasTradeIn())
                                            <div class="product-page__option option row">
                                                <div class="option__group col-xs-12">
                                                    <label class="option__group-name">Выберите:</label>
                                                    <div>
                                                        <label class="option__item" title="- {{ App\Support\Price::format($product->tradeInDiscount()) }}">
                                                            <input type="checkbox" name="trade_in" value="1" x-model="tradeIn" />
                                                            <span class="option__name"><i class="fa fa-recycle fa-fw"></i> <span class="option__tit">Трейд-ин</span> <span class="option__val"> {{ App\Support\Price::format($product->priceFor(true)) }} </span></span>
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
                                            @foreach ($specificationGroups->take(6) as $name => $values)
                                                <div class="product-data__item"><div class="product-data__item-div"><x-spec-name :attribute="$values->first()->attribute" /></div><x-spec-values :values="$values" /></div>
                                                @if ($caseType && $name === $caseTypeAfter)
                                                    <x-case-type-row :code="$caseType" />
                                                @endif
                                            @endforeach
                                            @if ($caseType && $caseTypeAfter === false)
                                                <x-case-type-row :code="$caseType" />
                                            @endif
                                        </div>
                                        <a class="product-page__more-attr" href="#tab-specification" @click.prevent="$showTab('specification')">Все характеристики</a>
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
                    <div x-data="{tab: @js($product->hasDescription() ? 'description' : ($specifications->isNotEmpty() ? 'specification' : 'question'))}" x-on:show-tab.window="tab = $event.detail">
                        <ul class="product-page-tabs nav nav-tabs" role="tablist">
                            @if ($product->hasDescription())
                                <li :class="{active: tab === 'description'}"><a href="#tab-description" @click.prevent="tab = 'description'">Описание</a></li>
                            @endif
                            @if ($specifications->isNotEmpty())
                                <li :class="{active: tab === 'specification'}"><a href="#tab-specification" @click.prevent="tab = 'specification'">Характеристики</a></li>
                            @endif
                            <li :class="{active: tab === 'question'}"><a href="#tab-question" class="tab-question" @click.prevent="tab = 'question'">Вопрос-ответ</a></li>
                        </ul>
                        <div class="tab-content">
                            @if ($product->hasDescription())
                                <div class="tab-pane" :class="{active: tab === 'description'}" id="tab-description">{!! $product->description !!}</div>
                            @endif
                            @if ($specifications->isNotEmpty())
                                <div class="tab-pane" :class="{active: tab === 'specification'}" id="tab-specification">
                                    <div class="product-data">
                                        @foreach ($specificationGroups as $name => $values)
                                            <div class="product-data__item">
                                                <div class="product-data__item-div"><x-spec-name :attribute="$values->first()->attribute" /></div>
                                                <div class="product-data__item-div"><x-spec-values :values="$values" /></div>
                                            </div>
                                            @if ($caseType && $name === $caseTypeAfter)
                                                <x-case-type-row :code="$caseType" layout="tab" />
                                            @endif
                                        @endforeach
                                        @if ($caseType && $caseTypeAfter === false)
                                            <x-case-type-row :code="$caseType" layout="tab" />
                                        @endif
                                    </div>
                                </div>
                            @endif
                            <div class="tab-pane" :class="{active: tab === 'question'}" id="tab-question">
                                <div class="question-info">
                                    <p>Есть вопрос о товаре? Напишите — мы перезвоним и ответим.</p>
                                    <a href="{{ route('product-question.create', $product) }}" class="btn btn-sm btn-primary" @click.prevent="$store.modal.open($el.href, 'Задать вопрос')">Задать вопрос</a>
                                </div>
                            </div>
                        </div>
                        @if ($guide)
                            <p class="product-page__guide-note"><i class="far fa-lightbulb" aria-hidden="true"></i> Не уверены, подойдёт ли аккумулятор? <a href="{{ route('guide.show', $guide) }}">{{ $guide->title }}</a></p>
                        @endif
                    </div>
                </div>

                @if ($carLinks['models'] !== [])
                    <section class="car-landing__section product-page__fits">
                        <div class="heading">Подходит для автомобилей</div>
                        <ul class="car-landing__chips">
                            @foreach ($carLinks['models'] as $model)
                                <li><a href="{{ $model['url'] }}" class="car-landing__chip">{{ $model['name'] }}</a></li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($similarProducts->isNotEmpty())
                    <div class="heading">Похожие товары</div>
                    <div class="uni-module similar-products scroll-carousel" x-data="scrollCarousel({items: {0: 2, 700: 3, 992: 4, 1400: 5}})">
                        <div class="uni-module__wrapper scroll-carousel__track" x-ref="track">
                            @foreach ($similarProducts as $similarProduct)
                                @include('partials.product-card', ['product' => $similarProduct])
                            @endforeach
                        </div>
                        <button type="button" class="scroll-carousel__btn scroll-carousel__btn--prev" aria-label="Назад" :disabled="!canPrev" x-show="pages > 1" x-cloak @click="prev()"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                        <button type="button" class="scroll-carousel__btn scroll-carousel__btn--next" aria-label="Вперёд" :disabled="!canNext" x-show="pages > 1" x-cloak @click="next()"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                        <div class="scroll-carousel__dots" x-show="pages > 1" x-cloak>
                            <template x-for="n in pages" :key="n">
                                <button type="button" class="scroll-carousel__dot" :class="{'is-active': page === n - 1}" :aria-label="'Страница ' + n" @click="go(n - 1)"></button>
                            </template>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
