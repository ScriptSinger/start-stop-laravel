@extends('layouts.app')

@section('robots', 'noindex, nofollow')

@push('page-styles')
    @vite('resources/css/storefront/pages/account.css')
@endpush

{{-- Разметка — как account/order_list.twig темы UniShop2 старого сайта. --}}
@section('content')
    <div id="account-order" class="container">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li><a href="{{ route('account') }}">Личный кабинет</a></li>
                <li>История заказов</li>
            </ul>
            <h1>История заказов</h1>
        </div>
        <div class="row">
            <div id="content" class="col-sm-8 col-md-9 col-lg-9 col-xxl-16">
                <div class="uni-wrapper">
                    @if ($orders->isEmpty())
                        <div class="div-text-empty">Вы ещё не делали заказов.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <td class="text-right">№ заказа</td>
                                        <td class="text-left">Дата</td>
                                        <td class="text-right">Товаров</td>
                                        <td class="text-left">Статус</td>
                                        <td class="text-right">Сумма</td>
                                        <td></td>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($orders as $order)
                                        <tr>
                                            <td class="text-right">#{{ $order->id }}</td>
                                            <td class="text-left">{{ $order->created_at?->format('d.m.Y') }}</td>
                                            <td class="text-right">{{ $order->items_count }}</td>
                                            <td class="text-left">{{ $order->status->toString() }}</td>
                                            <td class="text-right order-summary__price">{{ number_format((float) $order->total, 0, '', '') }}р.</td>
                                            <td class="text-right"><a href="{{ route('account.order', $order->id) }}" class="btn btn-default btn-sm" title="Подробнее" aria-label="Подробнее о заказе #{{ $order->id }}"><i class="fa fa-eye"></i></a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        {{ $orders->links() }}
                    @endif
                </div>
            </div>
            @include('partials.account-menu')
        </div>
    </div>
@endsection
