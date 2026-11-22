@extends('layouts.app')

{{-- Разметка и тексты — как common/success.twig темы UniShop2 старого сайта
     (вариант для гостя: личного кабинета у нас нет) плюс состав заказа. --}}
@section('content')
    <div class="container">
        <ul class="breadcrumb">
            <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
            <li>Заказ сформирован</li>
        </ul>
        <div class="row">
            {{-- Данные для Метрики: цель «order» и покупка (см. trackPlacedOrder в embeds.js). --}}
            <div id="content" class="col-sm-12" data-metrika-purchase="{{ json_encode([
                'id' => $order->id,
                'revenue' => (float) $order->total,
                'products' => $order->items->map(fn ($item): array => [
                    'id' => (string) $item->product_id,
                    'name' => $item->name,
                    'price' => (float) $item->price,
                    'quantity' => (int) $item->quantity,
                ])->all(),
            ], JSON_UNESCAPED_UNICODE) }}">
                <div class="order-success__head">
                    <i class="fa fa-check order-success__icon" aria-hidden="true"></i>
                    <div class="order-success__intro">
                        <h1 class="order-success__heading">Ваш заказ #{{ $order->id }} сформирован!</h1>
                        <p class="order-success__lead">Менеджер перезвонит на номер <strong class="order-success__phone">{{ $order->customer_phone }}</strong>, чтобы подтвердить заказ.
                            Пожалуйста, задавайте <a href="{{ route('contact') }}">нам</a> любые вопросы, которые у вас возникают.</p>
                    </div>
                    <div class="order-success__sum">
                        <span class="order-success__sum-label">Сумма заказа</span>
                        <span class="order-success__sum-value">{{ App\Support\Price::format((float) $order->total) }}</span>
                    </div>
                </div>

                <ol class="order-success__steps">
                    <li class="order-success__step is-done"><span class="order-success__step-mark"><i class="fa fa-check" aria-hidden="true"></i></span>Заказ принят</li>
                    <li class="order-success__step"><span class="order-success__step-mark">2</span>Звонок менеджера</li>
                    <li class="order-success__step"><span class="order-success__step-mark">3</span>Получение заказа</li>
                </ol>

                <div class="row">
                    <div class="col-md-7">
                        <div class="order-success__card">
                            <h2 class="order-success__title">Состав заказа</h2>
                            <div class="table-responsive">
                                <table class="table order-summary">
                                    @foreach ($order->items as $item)
                                        <tr>
                                            <td>
                                                <div class="order-summary__item">
                                                    @if ($item->product)
                                                        <a href="{{ route('product.show', $item->product) }}" class="order-summary__thumb"><img src="{{ App\Services\Catalog\ProductMedia::url($item->product->image ?: 'no_image.png') }}" alt="{{ $item->name }}" width="64" height="64" loading="lazy" /></a>
                                                    @endif
                                                    <div>
                                                        @if ($item->product)
                                                            <a href="{{ route('product.show', $item->product) }}">{{ $item->name }}</a>
                                                        @else
                                                            {{ $item->name }}
                                                        @endif
                                                        × {{ $item->quantity }}
                                                        @if ($item->trade_in_discount)
                                                            <div><span class="order-summary__badge">Трейд-ин: со сдачей старого АКБ</span></div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-right order-summary__price">{{ App\Support\Price::format((float) $item->total) }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="order-summary__total">
                                        <td><strong>Всего</strong></td>
                                        <td class="text-right order-summary__price"><strong>{{ App\Support\Price::format((float) $order->total) }}</strong></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="order-success__card">
                            <h2 class="order-success__title">Получение и оплата</h2>
                            <dl class="order-success__details">
                                <dt>Способ получения</dt>
                                <dd>{{ $order->delivery_method }}@if ($order->shipping_address), {{ $order->shipping_address }}@endif</dd>
                                <dt>Способ оплаты</dt>
                                <dd>{{ $order->payment_method }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <div class="order-success__actions">
                    <a href="{{ route('home') }}" class="btn btn-primary">Вернуться на главную</a>
                </div>
            </div>
        </div>
    </div>
@endsection
