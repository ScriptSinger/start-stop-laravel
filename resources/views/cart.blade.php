@extends('layouts.app')

@section('title', 'Корзина — '.config('shop.name'))

@php($availabilityLabels = [
    'in_stock' => ['В наличии', 'text-success'],
    'on_order' => ['Под заказ', 'text-warning'],
    'out_of_stock' => ['Нет в наличии — уточним срок по телефону', 'text-muted'],
])

@section('content')
    <div class="container">
        <ul class="breadcrumb">
            <li><a href="{{ route('home') }}">Главная</a></li>
            <li>Корзина</li>
        </ul>

        <h1>Корзина</h1>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($lines->isEmpty())
            <div class="div-text-empty">
                В корзине пока ничего нет. <a href="{{ route('home') }}">Перейти в каталог</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table cart-table">
                    <thead>
                        <tr>
                            <th>Товар</th>
                            <th class="text-right">Цена</th>
                            <th style="width: 210px;">Количество</th>
                            <th class="text-right">Сумма</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $line)
                            @php([$availabilityText, $availabilityClass] = $availabilityLabels[$line->availability()])
                            <tr>
                                <td>
                                    <a href="{{ route('product.show', $line->product) }}">{{ $line->product->name }}</a>
                                    <div class="small {{ $availabilityClass }}">{{ $availabilityText }}</div>
                                    @if ($line->product->is_pickup_only)
                                        <div class="small text-muted">Только самовывоз</div>
                                    @endif
                                </td>
                                <td class="text-right" style="white-space: nowrap;">
                                    {{ number_format($line->unitPrice(), 0, ',', ' ') }} р.
                                </td>
                                <td>
                                    <form method="post" action="{{ route('cart.update', $line->product) }}" class="cart-line-form">
                                        @csrf
                                        @method('PATCH')
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="quantity" value="{{ $line->quantity }}" min="0" max="{{ \App\Services\Cart\Cart::MAX_QUANTITY }}" class="form-control" aria-label="Количество">
                                            <span class="input-group-btn">
                                                <button type="submit" class="btn btn-default" title="Обновить"><i class="fas fa-sync"></i></button>
                                            </span>
                                        </div>
                                        @if ($line->product->hasTradeIn())
                                            <div class="checkbox small" style="margin: 5px 0 0;">
                                                <label>
                                                    <input type="checkbox" name="trade_in" value="1" @checked($line->tradeIn) onchange="this.form.submit()">
                                                    Сдаю старый АКБ (−{{ number_format((float) $line->product->trade_in_discount, 0, ',', ' ') }} р.)
                                                </label>
                                            </div>
                                        @endif
                                    </form>
                                </td>
                                <td class="text-right" style="white-space: nowrap;">
                                    <strong>{{ number_format($line->total(), 0, ',', ' ') }} р.</strong>
                                </td>
                                <td class="text-right">
                                    <form method="post" action="{{ route('cart.destroy', $line->product) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-link btn-sm" title="Удалить"><i class="fas fa-times"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-right"><strong>Итого:</strong></td>
                            <td class="text-right" style="white-space: nowrap;"><strong>{{ number_format($total, 0, ',', ' ') }} р.</strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if ($isPickupOnly)
                <div class="alert alert-info">В корзине есть товар, который можно забрать только самовывозом — доставка для этого заказа недоступна.</div>
            @endif

            <div class="text-right">
                <a href="{{ route('home') }}" class="btn btn-default">Продолжить покупки</a>
                <a href="{{ route('checkout.create') }}" class="btn btn-primary btn-lg">Оформить заказ</a>
            </div>
        @endif
    </div>
@endsection
