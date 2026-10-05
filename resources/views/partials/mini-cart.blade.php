{{-- Мини-корзина — содержимое .header-cart__dropdown (common/cart.twig темы
     UniShop2). Показывается в окне «Корзина» по клику на значок в шапке и
     после «В корзину». Ожидает $cartLines и $cartTotal. --}}
@if ($cartLines->isNotEmpty())
    <div class="header-cart__wrapper" data-products="{{ $cartLines->map(fn ($line) => $line->product->id)->implode(',') }}">
        @foreach ($cartLines as $line)
            @php($productUrl = route('product.show', $line->product))
            <div class="header-cart__item">
                <div class="header-cart__image">
                    <a href="{{ $productUrl }}"><img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($line->product->image ?: 'no_image.png') }}" alt="{{ $line->product->name }}" title="{{ $line->product->name }}" class="img-responsive" width="60" height="60" loading="lazy" /></a>
                </div>
                <form method="post" action="{{ route('cart.update', $line->product) }}" class="header-cart__item-wrapper" data-mini-cart>
                    @csrf
                    @method('PATCH')
                    @if ($line->tradeIn)
                        <input type="hidden" name="trade_in" value="1" />
                    @endif
                    <div class="header-cart__name">
                        <a href="{{ $productUrl }}">{{ $line->product->name }}</a>
                        @if ($line->tradeIn)
                            <div class="header-cart__option-item">Трейд-ин: сдаю старый АКБ</div>
                        @endif
                    </div>
                    <div class="header-cart__quantity">
                        <div class="qty-switch qty-switch__cart">
                            <i class="qty-switch__btn fa fa-minus" data-step="-1"></i>
                            <input type="text" name="quantity" value="{{ $line->quantity }}" data-minimum="1" data-maximum="{{ \App\Services\Cart\Cart::MAX_QUANTITY }}" class="qty-switch__input form-control" aria-label="Количество" inputmode="numeric" />
                            <i class="qty-switch__btn fa fa-plus" data-step="1"></i>
                        </div>
                    </div>
                    <div class="header-cart__price hidden-xs"><div class="header-cart__price-text">Цена за шт</div>{{ number_format($line->unitPrice(), 0, '', '') }}р.</div>
                    <div class="header-cart__total"><div class="header-cart__total-text hidden-xs">Всего</div>{{ number_format($line->total(), 0, '', '') }}р.</div>
                </form>
                <form method="post" action="{{ route('cart.destroy', $line->product) }}" class="header-cart__remove" data-mini-cart>
                    @csrf
                    @method('DELETE')
                    <button type="submit" title="Удалить" aria-label="Удалить" class="header-cart__remove-btn"><i class="far fa-trash-alt"></i></button>
                </form>
            </div>
        @endforeach
    </div>
    <div class="header-cart__totals">
        <div class="header-cart__totals-item">
            <div class="header-cart__totals-title">Итого:</div>
            <div class="header-cart__totals-text">{{ number_format($cartTotal, 0, '', '') }}р.</div>
        </div>
        <div class="header-cart__totals-item">
            <div class="header-cart__totals-title">Всего:</div>
            <div class="header-cart__totals-text">{{ number_format($cartTotal, 0, '', '') }}р.</div>
        </div>
    </div>
    <div class="header-cart__buttons">
        <button type="button" class="btn btn-lg btn-default" data-dismiss="modal">Продолжить покупки</button>
        <a href="{{ route('cart.index') }}" class="btn btn-lg btn-primary">Перейти к оформлению заказа</a>
    </div>
@else
    <div class="header-cart__empty"><i class="header-cart__icon-empty fas fa-shopping-bag"></i><br />Ваша корзина пуста!</div>
@endif
