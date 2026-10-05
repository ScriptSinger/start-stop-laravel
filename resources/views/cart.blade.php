@extends('layouts.app')

@section('title', 'Оформление заказа — '.config('shop.name'))

@push('module-styles')
    <link href="{{ asset('theme/stylesheet/checkout.css') }}" rel="stylesheet" media="screen" />
@endpush

@use('App\Enums\DeliveryMethod')
@use('App\Enums\PaymentMethod')
@php($deliveryMethods = $isPickupOnly ? [DeliveryMethod::Pickup] : [DeliveryMethod::Pickup, DeliveryMethod::City])
@php($selectedDelivery = DeliveryMethod::tryFrom((string) old('delivery')) ?? DeliveryMethod::Pickup)
@php($selectedDelivery = in_array($selectedDelivery, $deliveryMethods, true) ? $selectedDelivery : DeliveryMethod::Pickup)
@php($selectedPayment = old('payment', PaymentMethod::Transfer->value))
@php($agreementPage = config('shop.checkout_agreement_page'))

{{-- На старом сайте корзина и оформление — одна страница (checkout/uni_checkout
     темы UniShop2): слева товары и форма, справа «Ваш заказ» с кнопкой. --}}
@section('content')
    <div class="container">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li>Оформление заказа</li>
            </ul>
            <h1>Оформление заказа</h1>
        </div>

        <div id="content">
            <div class="uni-wrapper">
                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

                @if ($lines->isEmpty())
                    <div class="div-text-empty">Ваша корзина пуста!</div>
                    <a href="{{ route('home') }}" class="btn btn-lg btn-primary">Продолжить</a>
                @else
                    <div class="unicheckout__wrapper row">
                        <div class="unicheckout__forms col-sm-12 col-md-9 col-lg-9 col-xxl-15">
                            <div id="unicart">
                                @if ($errors->any())
                                    <div class="alert alert-danger">
                                        @foreach ($errors->all() as $message)
                                            <div>{{ $message }}</div>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="checkout-cart__wrapper">
                                    <div class="checkout-cart">
                                        @foreach ($lines as $line)
                                            @php($productUrl = route('product.show', $line->product))
                                            <div class="checkout-cart__item">
                                                <div class="checkout-cart__image">
                                                    <a href="{{ $productUrl }}"><img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($line->product->image ?: 'no_image.png') }}" alt="{{ $line->product->name }}" title="{{ $line->product->name }}" class="checkout-cart__image-img img-responsive" width="90" height="90" loading="lazy" /></a>
                                                </div>
                                                <form method="post" action="{{ route('cart.update', $line->product) }}" class="checkout-cart__item-wrapper">
                                                    @csrf
                                                    @method('PATCH')
                                                    <div class="checkout-cart__name">
                                                        <a href="{{ $productUrl }}">{{ $line->product->name }}</a>
                                                        @if ($line->availability() !== 'in_stock')
                                                            <div class="checkout-cart__option">{{ $line->availability() === 'on_order' ? 'Под заказ' : 'Нет в наличии — уточним срок по телефону' }}</div>
                                                        @endif
                                                        @if ($line->product->is_pickup_only)
                                                            <div class="checkout-cart__option">Только самовывоз</div>
                                                        @endif
                                                        @if ($line->product->hasTradeIn())
                                                            <label class="checkout-cart__option checkout-cart__trade-in">
                                                                <input type="checkbox" name="trade_in" value="1" @checked($line->tradeIn) onchange="this.form.submit()" />
                                                                Трейд-ин: сдаю старый АКБ (−{{ number_format((float) $line->product->trade_in_discount, 0, '', '') }}р.)
                                                            </label>
                                                        @endif
                                                    </div>
                                                    <div class="checkout-cart__quantity">
                                                        <div class="qty-switch qty-switch__cart">
                                                            <i class="qty-switch__btn fa fa-minus" data-step="-1"></i>
                                                            <input type="text" name="quantity" value="{{ $line->quantity }}" data-minimum="1" data-maximum="{{ \App\Services\Cart\Cart::MAX_QUANTITY }}" class="qty-switch__input form-control" aria-label="Количество" inputmode="numeric" />
                                                            <i class="qty-switch__btn fa fa-plus" data-step="1"></i>
                                                        </div>
                                                    </div>
                                                    <div class="checkout-cart__price hidden-xs"><div class="checkout-cart__price-text">Цена за шт</div>{{ number_format($line->unitPrice(), 0, '', '') }}р.</div>
                                                    <div class="checkout-cart__total"><div class="checkout-cart__total-text hidden-xs">Всего</div>{{ number_format($line->total(), 0, '', '') }}р.</div>
                                                </form>
                                                <form method="post" action="{{ route('cart.destroy', $line->product) }}" class="checkout-cart__remove">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" title="Удалить" aria-label="Удалить" class="checkout-cart__remove-btn"><i class="checkout-cart__remove-icon far fa-trash-alt"></i></button>
                                                </form>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div style="height:20px"></div>
                                </div>
                            </div>

                            <form method="post" action="{{ route('checkout.store') }}" id="unicheckout__form" class="unicheckout__form" novalidate>
                                @csrf
                                <div class="unicheckout__user">
                                    <div class="heading">Контактные данные</div>
                                    <div class="user_data checkout-customer">
                                        <div class="row-flex">
                                            @foreach ([
                                                ['name', 'text', 'Ваше имя *', 'given-name'],
                                                ['lastname', 'text', 'Ваша фамилия', 'family-name'],
                                                ['phone', 'tel', 'Контактный телефон *', 'tel'],
                                                ['email', 'email', 'Ваш e-mail', 'email'],
                                            ] as [$field, $type, $placeholder, $autocomplete])
                                                <input type="{{ $type }}" name="{{ $field }}" value="{{ old($field) }}" placeholder="{{ $placeholder }}" aria-label="{{ trim($placeholder, ' *') }}" autocomplete="{{ $autocomplete }}" class="checkout-customer__input form-control @error($field) input-warning @enderror" />
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                <div class="heading">Способ получения</div>
                                <input type="hidden" name="delivery" value="{{ $selectedDelivery->value }}" />
                                <ul class="unicheckout__pickup-nav nav nav-tabs">
                                    @foreach ($deliveryMethods as $method)
                                        <li class="{{ $method === $selectedDelivery ? 'active' : '' }}"><a href="#unicheckout-method-{{ $method->value }}" class="{{ $method === DeliveryMethod::Pickup ? 'unicheckout__pickup' : 'unicheckout__delivery' }}" data-toggle="tab" data-delivery="{{ $method->value }}">{{ $method === DeliveryMethod::Pickup ? 'Самовывоз' : 'Доставка' }}</a></li>
                                    @endforeach
                                </ul>
                                <div class="tab-content">
                                    <div id="unicheckout-method-pickup" class="tab-pane {{ $selectedDelivery === DeliveryMethod::Pickup ? 'active' : '' }}">
                                        <div class="unicheckout__pickup-item">
                                            <div class="unicheckout__pickup-title">Магазин «{{ config('shop.name') }}»</div>
                                            <div class="unicheckout__pickup-address">{{ config('shop.address') }}</div>
                                            <div class="unicheckout__pickup-time-life">
                                                <div class="unicheckout__pickup-time"><span class="unicheckout__pickup-time-span">Время работы</span> 10-20</div>
                                                <div class="unicheckout__pickup-life"><span class="unicheckout__pickup-life-span">Срок хранения</span> 3 дня</div>
                                            </div>
                                        </div>
                                        @if ($isPickupOnly)
                                            <p class="help-block">В корзине есть товар, который можно забрать только самовывозом.</p>
                                        @endif
                                    </div>
                                    @unless ($isPickupOnly)
                                        <div id="unicheckout-method-city" class="tab-pane {{ $selectedDelivery === DeliveryMethod::City ? 'active' : '' }}">
                                            <div class="unicheckout__address payment-address">
                                                <div class="heading">Адрес доставки</div>
                                                <div class="checkout-address-new row-flex">
                                                    <input type="text" name="address" value="{{ old('address') }}" placeholder="Ваш адрес *" aria-label="Адрес доставки" autocomplete="street-address" class="checkout-address-new__input form-control @error('address') input-warning @enderror" />
                                                </div>
                                            </div>
                                            <div class="unicheckout__shipping">
                                                <div class="heading">Способы доставки</div>
                                                <div class="shipping-method">
                                                    <div class="shipping-method__item radio">
                                                        <label class="shipping-method__label input">
                                                            <input type="radio" checked disabled />
                                                            <span class="shipping-method__quote">{{ DeliveryMethod::City->label() }} — бесплатно</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endunless
                                </div>

                                <div class="unicheckout__payment">
                                    <div class="heading">Способы оплаты</div>
                                    <div class="payment-method">
                                        @foreach ([PaymentMethod::Transfer, PaymentMethod::Card, PaymentMethod::Cash] as $method)
                                            <div class="radio" @unless ($method->isAllowedFor(DeliveryMethod::City)) data-pickup-only @endunless>
                                                <label class="input">
                                                    <input type="radio" name="payment" value="{{ $method->value }}" @checked($selectedPayment === $method->value) />
                                                    <div class="payment-method__title">{{ $method->label() }}</div>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="unicheckout__comment">
                                    <div class="heading">Комментарий</div>
                                    <textarea name="comment" rows="3" aria-label="Комментарий" class="checkout-comment form-control @error('comment') input-warning @enderror">{{ old('comment') }}</textarea>
                                </div>
                            </form>
                        </div>

                        <div class="unicheckout-sticky col-sm-12 col-md-3 col-lg-3 col-xxl-5">
                            <div class="unicheckout-sticky__wrapper">
                                <div class="unicheckout-sticky__heading">Ваш заказ</div>
                                <div class="unicheckout-sticky__total">
                                    <div class="unicheckout-sticky__total-item product-total">
                                        <div class="unicheckout-sticky__total-title">Товаров в корзине: </div>
                                        <div class="unicheckout-sticky__total-text">{{ $lines->sum('quantity') }}</div>
                                    </div>
                                    <div class="unicheckout-sticky__total-item sub_total">
                                        <div class="unicheckout-sticky__total-title">Итого</div>
                                        <div class="unicheckout-sticky__total-text">{{ number_format($total, 0, '', '') }}р.</div>
                                    </div>
                                    <div class="unicheckout-sticky__total-item total">
                                        <div class="unicheckout-sticky__total-title">Всего</div>
                                        <div class="unicheckout-sticky__total-text">{{ number_format($total, 0, '', '') }}р.</div>
                                    </div>
                                </div>
                                <div class="unicheckout-sticky__confirm">
                                    <div class="unicheckout-sticky__confirm-agree">
                                        <label class="input @error('agree') text-danger @enderror"><input type="checkbox" name="agree" value="1" form="unicheckout__form" @checked(old('agree')) class="unicheckout-sticky__confirm-input" />Я прочитал(-а) <a href="{{ route('page.show', $agreementPage) }}" target="_blank"><b>Политика безопасности</b></a> и согласен(-на) с условиями</label>
                                    </div>
                                    <button type="submit" form="unicheckout__form" id="confirm_checkout" class="unicheckout-sticky__confirm-btn btn btn-xl btn-block btn-primary">Оформить заказ</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Количество: «+» и «−» сразу пересчитывают корзину (как в теме).
        $('.checkout-cart .qty-switch__btn').on('click', function () {
            const input = $(this).siblings('.qty-switch__input');
            const value = (parseInt(input.val(), 10) || 1) + Number($(this).data('step'));

            input.val(Math.min(Math.max(value, input.data('minimum')), input.data('maximum')));
            input.closest('form').trigger('submit');
        });
        $('.checkout-cart .qty-switch__input').on('change', function () {
            $(this).closest('form').trigger('submit');
        });

        // Вкладки «Самовывоз / Доставка» задают способ получения; карта — только
        // при самовывозе. Сервер проверяет то же самое — здесь только удобство.
        (function ($) {
            const form = $('#unicheckout__form');
            const pickup = @json(DeliveryMethod::Pickup->value);

            function sync() {
                const isPickup = form.find('input[name=delivery]').val() === pickup;

                form.find('[data-pickup-only]').each(function () {
                    const input = $(this).find('input');

                    input.prop('disabled', !isPickup);
                    $(this).toggleClass('disabled', !isPickup);

                    if (!isPickup && input.is(':checked')) {
                        form.find('input[name=payment]').not(input).first().prop('checked', true);
                    }
                });
            }

            form.find('[data-delivery]').on('shown.bs.tab', function () {
                form.find('input[name=delivery]').val($(this).data('delivery'));
                sync();
            });

            // Поле с ошибкой подсвечено, пока его не исправят (form_error темы).
            form.on('input change', '.input-warning', function () {
                $(this).removeClass('input-warning');
            });

            sync();
        })(jQuery);
    </script>
@endpush
