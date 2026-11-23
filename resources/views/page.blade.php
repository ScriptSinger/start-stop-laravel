@extends('layouts.app')

{{-- Разметка — 1:1 с information/information.twig темы UniShop2 старого сайта. --}}
@section('content')
    <div class="container">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li>{{ $page->title }}</li>
            </ul>
            <h1>{{ $page->heading ?: $page->title }}</h1>
        </div>
        <div class="row">
            <div id="content" class="col-sm-12">
                <div class="article_description uni-wrapper">
                    {!! $content !!}
                </div>
                @if (in_array($page->slug, config('shop.pages_with_contacts'), true))
                    <div class="content-bottom">
                        @include('home.reviews')
                        @include('home.contacts')
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
