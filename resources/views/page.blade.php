@extends('layouts.app')

@section('title', $page->meta_title ?: $page->title.' — '.config('shop.name'))
{{-- Строкой: при null Blade открыл бы секцию и не закрыл буфер вывода. --}}
@section('meta_description', (string) $page->meta_description)

@section('body_class', 'menu-expanded')

{{-- Разметка — 1:1 с information/information.twig темы UniShop2 старого сайта. --}}
@section('content')
    <div class="container">
        <div class="breadcrumb-h1 col-md-offset-3 col-lg-offset-3 col-xxl-offset-4">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li>{{ $page->title }}</li>
            </ul>
            <h1>{{ $page->heading ?: $page->title }}</h1>
        </div>
        <div class="row">
            <aside id="column-left" class="col-sm-4 col-md-3 col-lg-3 col-xxl-4 hidden-xs hidden-sm"></aside>
            <div id="content" class="col-sm-12 col-md-9 col-lg-9 col-xxl-16">
                <div class="article_description uni-wrapper">
                    {!! $page->description !!}
                </div>
                @if (in_array($page->slug, config('shop.pages_with_contacts'), true))
                    <div class="content-bottom">
                        @include('home.reviews')
                        @include('home.map')
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
