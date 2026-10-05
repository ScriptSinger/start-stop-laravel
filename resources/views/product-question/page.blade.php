@extends('layouts.app')

@section('title', 'Вопрос о товаре — '.config('shop.name'))

@section('content')
    <div class="container">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li><a href="{{ route('product.show', $product) }}">{{ $product->name }}</a></li>
                <li>Задать вопрос</li>
            </ul>
            <h1>Задать вопрос</h1>
        </div>
        <div class="row">
            <div class="col-sm-6">
                @include('product-question.form')
            </div>
        </div>
    </div>
@endsection
