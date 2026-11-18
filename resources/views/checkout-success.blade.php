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
            <div id="content" class="col-sm-12">
                <div class="order-success__head">
                    <h1 class="order-success__heading">
                        <i class="fa fa-check-circle order-success__icon" aria-hidden="true"></i>
                        <span>Ваш заказ #{{ $order->id }} сформирован!</span>
                    </h1>
                    <p class="order-success__lead">Менеджер перезвонит на номер <strong class="order-success__phone">{{ $order->customer_phone }}</strong>, чтобы подтвердить заказ.
                        Пожалуйста, задавайте <a href="{{ route('contact') }}">нам</a> любые вопросы, которые у вас возникают.</p>
                </div>

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
