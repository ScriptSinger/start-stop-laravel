@extends('layouts.app')

@section('robots', 'noindex, nofollow')

@push('page-styles')
    @vite('resources/css/storefront/pages/account.css')
@endpush

{{-- Разметка — как account/order_info.twig темы UniShop2 старого сайта. --}}
@section('content')
    <div id="account-order-info" class="container">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li><a href="{{ route('account') }}">Личный кабинет</a></li>
                <li><a href="{{ route('account.orders') }}">История заказов</a></li>
                <li>Заказ #{{ $order->id }}</li>
            </ul>
            <h1>Заказ #{{ $order->id }}</h1>
        </div>
        <div class="row">
            <div id="content" class="col-sm-8 col-md-9 col-lg-9 col-xxl-16">
                <div class="uni-wrapper">
                    <table class="table table-bordered">
                        <tr><td><b>Дата:</b> {{ $order->created_at?->format('d.m.Y H:i') }}</td><td><b>Статус:</b> {{ $order->statusLabel() }}</td></tr>
                        <tr><td><b>Получение:</b> {{ $order->delivery_method ?: 'уточнит менеджер' }}@if ($order->shipping_address), {{ $order->shipping_address }}@endif</td><td><b>Оплата:</b> {{ $order->payment_method ?: 'уточнит менеджер' }}</td></tr>
                    </table>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr><td class="text-left">Товар</td><td class="text-right">Количество</td><td class="text-right">Цена</td><td class="text-right">Сумма</td></tr>
                            </thead>
                            <tbody>
                                @foreach ($order->items as $item)
                                    <tr>
                                        <td class="text-left">
                                            @if ($item->product?->status)
                                                <a href="{{ route('product.show', $item->product) }}">{{ $item->name }}</a>
                                            @else
                                                {{ $item->name }}
                                            @endif
                                            @if ($item->trade_in_discount)
                                                <div class="small text-muted">Трейд-ин: со сдачей старого АКБ</div>
                                            @endif
                                        </td>
                                        <td class="text-right">{{ $item->quantity }}</td>
                                        <td class="text-right order-summary__price">{{ number_format((float) $item->price, 0, '', '') }}р.</td>
                                        <td class="text-right order-summary__price">{{ number_format((float) $item->total, 0, '', '') }}р.</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr><td colspan="3" class="text-right"><b>Всего</b></td><td class="text-right order-summary__price"><b>{{ number_format((float) $order->total, 0, '', '') }}р.</b></td></tr>
                            </tfoot>
                        </table>
                    </div>
                    @if ($order->comment)
                        <p><b>Комментарий:</b> {{ $order->comment }}</p>
                    @endif
                    <a href="{{ route('account.orders') }}" class="btn btn-default">К истории заказов</a>
                </div>
            </div>
            @include('partials.account-menu')
        </div>
    </div>
@endsection
