@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li><a href="{{ route('product.show', $product) }}">{{ $product->name }}</a></li>
                <li>Быстрый заказ</li>
            </ul>
            <h1>Быстрый заказ</h1>
        </div>
        <div class="row">
            <div class="col-sm-6">
                @include('quick-order.form')
            </div>
        </div>
    </div>
@endsection
