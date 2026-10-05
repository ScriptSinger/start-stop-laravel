@extends('layouts.app')

@section('title', $page->title.' — '.config('shop.name'))

@section('content')
    <div class="container">
        <ul class="breadcrumb">
            <li><a href="{{ route('home') }}">Главная</a></li>
            <li>{{ $page->title }}</li>
        </ul>

        <h1>{{ $page->title }}</h1>

        <div class="page-content">
            {!! $page->description !!}
        </div>
    </div>
@endsection
