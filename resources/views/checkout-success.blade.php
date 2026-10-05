@extends('layouts.app')

@section('title', 'Заказ принят — '.config('shop.name'))

@section('content')
    <div class="container">
        <h1>Спасибо! Заказ №{{ $order->id }} принят</h1>

        <p>Менеджер перезвонит на номер {{ $order->customer_phone }}, чтобы подтвердить заказ.
            Если есть вопросы — звоните: <a href="tel:{{ preg_replace('/\D/', '', config('shop.phone')) }}">{{ config('shop.phone') }}</a>.</p>

        <table class="table" style="max-width: 640px;">
            @foreach ($order->items as $item)
                <tr>
                    <td>
                        {{ $item->name }} × {{ $item->quantity }}
                        @if ($item->trade_in_discount)
                            <div class="small text-muted">со сдачей старого АКБ</div>
                        @endif
                    </td>
                    <td class="text-right" style="white-space: nowrap;">{{ number_format((float) $item->total, 0, ',', ' ') }} р.</td>
                </tr>
            @endforeach
            <tr>
                <td><strong>Итого</strong></td>
                <td class="text-right" style="white-space: nowrap;"><strong>{{ number_format((float) $order->total, 0, ',', ' ') }} р.</strong></td>
            </tr>
        </table>

        <p><strong>Получение:</strong> {{ $order->delivery_method }}@if ($order->shipping_address), {{ $order->shipping_address }}@endif</p>
        <p><strong>Оплата:</strong> {{ $order->payment_method }}</p>

        <a href="{{ route('home') }}" class="btn btn-primary">Вернуться в каталог</a>
    </div>
@endsection
