@extends('layouts.app')

@section('title', ($search !== '' ? 'Поиск - '.$search : 'Поиск').' — '.config('shop.name'))

@push('module-styles')
    <link href="{{ asset('theme/stylesheet/search-page.css') }}" rel="stylesheet" media="screen" />
@endpush

{{-- Разметка — 1:1 с product/search.twig темы UniShop2 старого сайта. --}}
@section('content')
    <div class="container">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li>Поиск</li>
            </ul>
            <h1>{{ $search !== '' ? 'Поиск - '.$search : 'Поиск' }}</h1>
        </div>
        <div class="row">
            <div id="content" class="col-sm-12">
                <div class="uni-wrapper">
                    <form class="search-page__search-block" action="{{ route('search') }}" method="get">
                        <div class="row-flex">
                            <div class="search-page__search-input">
                                <input type="text" name="search" value="{{ $search }}" placeholder="Ключевые слова" id="input-search" class="form-control" aria-label="Ключевые слова" />
                                <button type="button" class="search-btn-clear {{ $search !== '' ? 'show' : '' }}" aria-label="Очистить">&times;</button>
                            </div>
                            <div class="search-page__search-delimiter visible-xs"></div>
                            <div class="search-page__search-category">
                                <select name="category_id" class="form-control" aria-label="Категория">
                                    <option value="0">Все категории</option>
                                    @foreach ($categoryOptions as $root)
                                        <option value="{{ $root->id }}" @selected($category?->is($root))>{{ $root->name }}</option>
                                        @foreach ($root->children as $child)
                                            <option value="{{ $child->id }}" @selected($category?->is($child))>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $child->name }}</option>
                                            @foreach ($child->children as $grandchild)
                                                <option value="{{ $grandchild->id }}" @selected($category?->is($grandchild))>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $grandchild->name }}</option>
                                            @endforeach
                                        @endforeach
                                    @endforeach
                                </select>
                            </div>
                            <div class="search-page__search-delimiter visible-xs"></div>
                            <div class="search-page__search-button">
                                <button type="submit" id="button-search" class="btn btn-primary btn-xl btn-block"><span>Поиск</span></button>
                            </div>
                        </div>
                    </form>

                    @if ($categoryLinks !== [])
                        <div class="heading">Категории</div>
                        <div class="category-list row row-flex">
                            @foreach ($categoryLinks as $link)
                                <div class="col-xxl-2-1 col-lg-2 col-md-2 col-sm-4 col-xs-4">
                                    <a href="{{ $link['url'] }}" class="category-list__item uni-item-bg" title="{{ isset($link['hint']) ? $link['hint'].' — '.$link['title'] : $link['title'] }}">
                                        <span class="category-list__name">{{ $link['title'] }}</span>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($search !== '')
                        <div class="heading">Товары</div>
                        @if ($foundInCategories->isNotEmpty())
                            <div class="product-category-list">
                                <h4>Найдено в категориях</h4>
                                @foreach ($foundInCategories as $foundIn)
                                    <a href="{{ route('search', ['search' => $search, 'category_id' => $foundIn->id]) }}" class="product-category-list__item">{{ $foundIn->name }}</a>
                                @endforeach
                            </div>
                        @endif
                    @endif

                    @if ($products->isEmpty())
                        <div class="div-text-empty">{{ $search !== '' ? 'Нет товаров, которые соответствуют критериям поиска.' : 'Введите запрос в строку поиска.' }}</div>
                    @else
                        @include('partials.catalog-sorts')

                        <div class="products-block row row-flex">
                            @foreach ($products as $product)
                                <div class="product-layout product-grid grid-view col-sm-6 col-md-3 col-lg-3 col-xxl-4">
                                    @include('partials.product-card', ['product' => $product])
                                </div>
                            @endforeach
                        </div>

                        {{ $products->links() }}
                        <div class="pagination-text">Показано с {{ $products->firstItem() }} по {{ $products->lastItem() }} из {{ $products->total() }} (всего {{ $products->lastPage() }} <x-plural :count="$products->lastPage()" forms="страница|страницы|страниц" />)</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
