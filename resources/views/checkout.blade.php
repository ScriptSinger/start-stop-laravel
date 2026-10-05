@extends('layouts.app')

@section('title', 'Оформление заказа — '.config('shop.name'))

@use('App\Enums\DeliveryMethod')
@use('App\Enums\PaymentMethod')
@php($deliveryMethods = $isPickupOnly ? [DeliveryMethod::Pickup] : DeliveryMethod::cases())
@php($selectedDelivery = old('delivery', $deliveryMethods[0]->value))

@section('content')
    <div class="container">
        <ul class="breadcrumb">
            <li><a href="{{ route('home') }}">Главная</a></li>
            <li><a href="{{ route('cart.index') }}">Корзина</a></li>
            <li>Оформление заказа</li>
        </ul>

        <h1>Оформление заказа</h1>

        @if ($errors->any())
            <div class="alert alert-danger">Проверьте, пожалуйста, отмеченные поля.</div>
        @endif

        <div class="row">
            <div class="col-md-7">
                <form method="post" action="{{ route('checkout.store') }}" id="checkout-form" novalidate>
                    @csrf

                    <h3>Контакты</h3>
                    @foreach ([
                        ['name', 'Имя', 'text', 'name', true],
                        ['phone', 'Телефон', 'tel', 'tel', true],
                        ['email', 'E-mail', 'email', 'email', false],
                    ] as [$field, $label, $type, $autocomplete, $required])
                        <div class="form-group @error($field) has-error @enderror">
                            <label for="checkout-{{ $field }}">{{ $label }}@if ($required) <span class="text-danger">*</span>@endif</label>
                            <input type="{{ $type }}" name="{{ $field }}" id="checkout-{{ $field }}" value="{{ old($field) }}"
                                   class="form-control" autocomplete="{{ $autocomplete }}" @required($required)>
                            @error($field)<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    @endforeach

                    <h3>Получение</h3>
                    <div class="form-group @error('delivery') has-error @enderror">
                        @foreach ($deliveryMethods as $method)
                            <div class="radio">
                                <label>
                                    <input type="radio" name="delivery" value="{{ $method->value }}" @checked($selectedDelivery === $method->value)>
                                    {{ $method->label() }}@if ($method->price() > 0) — {{ number_format($method->price(), 0, ',', ' ') }} р.@elseif ($method->needsAddress()) — бесплатно@endif
                                </label>
                            </div>
                        @endforeach
                        @if ($isPickupOnly)
                            <p class="help-block">В корзине есть товар, который можно забрать только самовывозом.</p>
                        @endif
                        @error('delivery')<span class="help-block">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group @error('address') has-error @enderror" id="checkout-address-group">
                        <label for="checkout-address">Адрес доставки <span class="text-danger">*</span></label>
                        <input type="text" name="address" id="checkout-address" value="{{ old('address') }}" class="form-control"
                               autocomplete="street-address" placeholder="Улица, дом, квартира">
                        @error('address')<span class="help-block">{{ $message }}</span>@enderror
                    </div>

                    <h3>Оплата</h3>
                    <div class="form-group @error('payment') has-error @enderror">
                        @foreach (PaymentMethod::cases() as $method)
                            <div class="radio" @unless ($method->isAllowedFor(DeliveryMethod::City)) data-pickup-only @endunless>
                                <label>
                                    <input type="radio" name="payment" value="{{ $method->value }}" @checked(old('payment', PaymentMethod::Cash->value) === $method->value)>
                                    {{ $method->label() }}
                                </label>
                            </div>
                        @endforeach
                        @error('payment')<span class="help-block">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group @error('comment') has-error @enderror">
                        <label for="checkout-comment">Комментарий к заказу</label>
                        <textarea name="comment" id="checkout-comment" rows="3" class="form-control">{{ old('comment') }}</textarea>
                        @error('comment')<span class="help-block">{{ $message }}</span>@enderror
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg">Подтвердить заказ</button>
                    <p class="help-block">Менеджер перезвонит, чтобы подтвердить заказ и время.</p>
                </form>
            </div>

            <div class="col-md-5">
                <h3>Ваш заказ</h3>
                <table class="table">
                    @foreach ($lines as $line)
                        <tr>
                            <td>
                                {{ $line->product->name }} × {{ $line->quantity }}
                                @if ($line->tradeInDiscount())
                                    <div class="small text-muted">со сдачей старого АКБ</div>
                                @endif
                            </td>
                            <td class="text-right" style="white-space: nowrap;">{{ number_format($line->total(), 0, ',', ' ') }} р.</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td><strong>Итого</strong></td>
                        <td class="text-right" style="white-space: nowrap;"><strong>{{ number_format($total, 0, ',', ' ') }} р.</strong></td>
                    </tr>
                </table>
                <a href="{{ route('cart.index') }}">Изменить корзину</a>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Адрес нужен только для доставки; карта — только при самовывозе.
        // Сервер проверяет то же самое — здесь только удобство.
        (function ($) {
            var needsAddress = @json(collect(DeliveryMethod::cases())->mapWithKeys(fn ($method) => [$method->value => $method->needsAddress()]));

            function sync() {
                var delivery = $('#checkout-form input[name=delivery]:checked').val();
                var isPickup = delivery === @json(DeliveryMethod::Pickup->value);

                $('#checkout-address-group').toggle(!!needsAddress[delivery]);

                $('#checkout-form [data-pickup-only]').each(function () {
                    var $input = $(this).find('input');
                    $input.prop('disabled', !isPickup);
                    $(this).toggleClass('disabled text-muted', !isPickup);
                    if (!isPickup && $input.is(':checked')) {
                        $('#checkout-form input[name=payment][value=' + @json(PaymentMethod::Cash->value) + ']').prop('checked', true);
                    }
                });
            }

            $('#checkout-form').on('change', 'input[name=delivery]', sync);
            sync();
        })(jQuery);
    </script>
@endpush
