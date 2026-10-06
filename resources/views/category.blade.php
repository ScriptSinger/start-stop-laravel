@extends('layouts.app')

@section('title', $category->meta_title ?: $category->name.' — '.config('shop.name'))
{{-- Строкой: при null Blade открыл бы секцию и не закрыл буфер вывода. --}}
@section('meta_description', (string) $category->meta_description)

@push('page-styles')
    @vite('resources/css/storefront/pages/category.css')
@endpush

{{-- Разметка — 1:1 с product/category.twig темы UniShop2 старого сайта. --}}
@section('content')
    <div id="product-category" class="container">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                @if ($category->parent)
                    <li><a href="{{ route('category.show', $category->parent) }}">{{ $category->parent->name }}</a></li>
                @endif
                <li>{{ $category->name }}</li>
            </ul>
            <h1>{{ $category->heading ?: $category->name }}</h1>
        </div>
        <div class="row">
            <aside id="column-left" class="col-sm-4 col-md-3 col-lg-3 col-xxl-4 hidden-xs">
                @include('partials.catalog-filter')
            </aside>
            <div id="content" class="col-sm-8 col-md-9 col-lg-9 col-xxl-16">
                <div class="content-top">
                    @include('home.banners')
                </div>
                <div class="uni-wrapper">
                    @if ($category->hasDescription())
                        <div class="category-info">{!! $category->description !!}</div>
                    @endif

                    @if ($categoryLinks !== [])
                        <div class="category-list row row-flex">
                            @foreach ($categoryLinks as $link)
                                <div class="col-xxl-2-1 col-lg-2 col-md-2 col-sm-4 col-xs-4">
                                    <a href="{{ $link['url'] }}" class="category-list__item uni-item-bg" title="{{ $link['title'] }}">
                                        <span class="category-list__name">{{ $link['title'] }}</span>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                        <div class="category-list__select visible-xs">
                            <select class="form-control" @change="location = $event.target.value" aria-label="Подкатегория">
                                <option value="">Выберите подкатегорию</option>
                                @foreach ($categoryLinks as $link)
                                    <option value="{{ $link['url'] }}">{{ $link['title'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @include('partials.catalog-sorts')

                    @if ($products->isEmpty())
                        <div class="div-text-empty">
                            @if ($filter->isActive())
                                По выбранным условиям ничего не нашлось.
                                <a href="{{ route('category.show', $category) }}">Сбросить фильтр</a>
                            @else
                                В этой категории пока нет товаров.
                            @endif
                        </div>
                    @else
                        @include('partials.product-grid', ['columnClass' => 'col-sm-6 col-md-4 col-lg-4 col-xxl-5'])
                    @endif
                </div>
                <div class="content-bottom">
                    @include('home.advantages')
                    @include('home.map')
                </div>
            </div>
        </div>
    </div>
@endsection
