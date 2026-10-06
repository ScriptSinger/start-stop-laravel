{{-- Карточка товара — разметка 1:1 с product-thumb темы UniShop2 старого сайта.
     Ожидает загруженную связь attributeValues.attribute (Product::withCardData()).
     Цены в формате старого сайта: «7300р.». --}}
@php($cardFormId = 'add-to-cart-'.$product->id)
@php($image = Illuminate\Support\Facades\Storage::disk('public')->url($product->image ?: 'no_image.png'))
@php([$availabilityText, $availabilityClass] = match (true) {
    $product->quantity > 0 => ['В наличии', 't-5'],
    $product->isAvailableOnOrder() => ['Под заказ', 't-2'],
    default => ['Нет в наличии', 't-1'],
})

{{-- livePrice: отметили трейд-ин — цена пересчитывается с анимацией, как в теме. --}}
<div class="product-thumb uni-item" x-data="livePrice({price: {{ $product->hasSpecial() ? (float) $product->price : $product->displayPrice() }}, special: {{ $product->hasSpecial() ? $product->displayPrice() : 'null' }}, tradeInDiscount: {{ (float) $product->trade_in_discount }}})">
    <div class="product-thumb__image">
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
        <a href="{{ route('product.show', $product) }}" title="{{ $product->name }}">
            <img src="{{ $image }}" alt="{{ $product->name }}" class="img-responsive" width="220" height="230" loading="lazy" />
        </a>
    </div>
    <div class="product-thumb__caption">
        <a class="product-thumb__name" href="{{ route('product.show', $product) }}">{{ $product->name }}</a>

        @if (($specifications = $product->specifications(6))->isNotEmpty())
            <div class="product-thumb__attribute product-thumb__description attribute">
                @foreach ($specifications as $name => $value)
                    {{ $name }}: <span class="product-thumb__attribute-value">{{ $value }}</span>
                @endforeach
            </div>
        @endif

        @if ($product->hasTradeIn())
            {{-- Галочка относится к форме «В корзину» (атрибут form): обмен
                 старого АКБ учитывается уже при добавлении из каталога. --}}
            <div class="product-thumb__option option">
                <div class="option__group">
                    <label class="option__item" data-toggle="tooltip" title="- {{ number_format((float) $product->trade_in_discount, 0, '', '') }}р.">
                        <input type="checkbox" name="trade_in" value="1" form="{{ $cardFormId }}" x-model="tradeIn" />
                        <span class="option__name"><i class="fa fa-recycle fa-fw"></i> <span class="option__tit hidden-xs">Трейд-ин</span> <span class="option__val"> {{ number_format($product->priceFor(true), 0, '', '') }}р. </span></span>
                    </label>
                </div>
            </div>
        @endif

        <div class="qty-indicator" data-text="Наличие:">
            <div class="qty-indicator__text {{ $availabilityClass }}"> {{ $availabilityText }} </div>
        </div>

        <div class="product-thumb__price price">
            @if ($product->hasSpecial())
                <span class="price-old" x-text="format(shownPrice)">{{ number_format((float) $product->price, 0, '', '') }}р.</span> <span class="price-new" x-text="format(shownSpecial)">{{ number_format($product->displayPrice(), 0, '', '') }}р.</span>
            @else
                <span x-text="format(shownPrice)">{{ number_format($product->displayPrice(), 0, '', '') }}р.</span>
            @endif
        </div>

        <form method="post" action="{{ route('cart.store', $product) }}" id="{{ $cardFormId }}" class="product-thumb__cart cart" @submit.prevent="$store.cart.add($el)">
            @csrf
            <button type="submit" class="product-thumb__add-to-cart add_to_cart btn" title="В корзину" :class="{in_cart: $store.cart.has({{ $product->id }})}"><x-cart-button-label :product="$product" /></button>
            <a href="{{ route('quick-order.create', $product) }}" class="product-thumb__quick-order quick-order btn" title="Быстрый заказ" aria-label="Быстрый заказ" @click.prevent="$store.modal.open($el.href, 'Быстрый заказ')"><i class="far fa-paper-plane"></i><span>Быстрый заказ</span></a>
            @if (Route::has('wishlist.store'))
                <button type="submit" class="product-thumb__wishlist wishlist" title="В закладки" formaction="{{ route('wishlist.store', $product) }}" :class="{active: $store.saved.has('wishlist', {{ $product->id }})}" :title="$store.saved.has('wishlist', {{ $product->id }}) ? 'В закладках' : 'В закладки'" @click.prevent="$store.saved.toggle('wishlist', {{ $product->id }}, $el)"><i class="far fa-heart"></i></button>
            @endif
            @if (Route::has('compare.store'))
                <button type="submit" class="product-thumb__compare compare" title="В сравнение" formaction="{{ route('compare.store', $product) }}" :class="{active: $store.saved.has('compare', {{ $product->id }})}" :title="$store.saved.has('compare', {{ $product->id }}) ? 'В сравнении' : 'В сравнение'" @click.prevent="$store.saved.toggle('compare', {{ $product->id }}, $el)"><i class="fas fa-align-right"></i></button>
            @endif
        </form>
    </div>
</div>
