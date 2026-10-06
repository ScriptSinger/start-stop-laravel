@extends('layouts.app')

@section('title', 'Ваш заказ #'.$order->id.' сформирован! — '.config('shop.name'))

{{-- Разметка и тексты — как common/success.twig темы UniShop2 старого сайта
     (вариант для гостя: личного кабинета у нас нет) плюс состав заказа. --}}
@section('content')
    <div class="container">
        <ul class="breadcrumb">
            <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
            <li><a href="{{ route('cart.index') }}">Корзина покупок</a></li>
            <li>Заказ сформирован</li>
        </ul>
        <div class="row">
            <div id="content" class="col-sm-12">
                <h1>Ваш заказ #{{ $order->id }} сформирован!</h1>
                <p>Ваш заказ успешно создан!</p>
                <p>Менеджер перезвонит на номер {{ $order->customer_phone }}, чтобы подтвердить заказ.
                    Пожалуйста, задавайте <a href="{{ route('contact') }}">нам</a> любые вопросы, которые у вас возникают.</p>
                <p>Спасибо за покупки в нашем интернет-магазине!</p>

                <div class="table-responsive">
                    <table class="table table-bordered order-summary">
                        @foreach ($order->items as $item)
                            <tr>
                                <td>
                                    {{ $item->name }} × {{ $item->quantity }}
                                    @if ($item->trade_in_discount)
                                        <div class="small text-muted">Трейд-ин: со сдачей старого АКБ</div>
                                    @endif
                                </td>
                                <td class="text-right order-summary__price">{{ number_format((float) $item->total, 0, '', '') }}р.</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td><strong>Всего</strong></td>
                            <td class="text-right order-summary__price"><strong>{{ number_format((float) $order->total, 0, '', '') }}р.</strong></td>
                        </tr>
                    </table>
                </div>

                <p><strong>Способ получения:</strong> {{ $order->delivery_method }}@if ($order->shipping_address), {{ $order->shipping_address }}@endif</p>
                <p><strong>Способ оплаты:</strong> {{ $order->payment_method }}</p>

                <div class="buttons">
                    <div class="pull-right"><a href="{{ route('home') }}" class="btn btn-primary">Продолжить</a></div>
                </div>
            </div>
        </div>
    </div>
@endsection
