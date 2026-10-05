@extends('layouts.app')

@section('title', $category->name.' — '.config('shop.name'))

@section('content')
    <div class="container">
        <ul class="breadcrumb">
            <li><a href="{{ route('home') }}">Главная</a></li>
            @if ($category->parent)
                <li><a href="{{ route('category.show', $category->parent) }}">{{ $category->parent->name }}</a></li>
            @endif
            <li>{{ $category->name }}</li>
        </ul>

        <div class="row">
            @include('partials.category-sidebar', ['activeCategory' => $category])

            <div class="col-sm-8 col-md-9">
                <h1>{{ $category->name }}</h1>

                @if ($category->description)
                    <div class="category-info__description">{!! $category->description !!}</div>
                @endif

                @if ($subcategories->isNotEmpty())
                    <div class="category-list row row-flex">
                        @foreach ($subcategories as $subcategory)
                            <div class="col-sm-3">
                                <a href="{{ route('category.show', $subcategory) }}" class="category-list__item uni-item-bg" title="{{ $subcategory->name }}">
                                    <span class="category-list__name">{{ $subcategory->name }}</span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($products->isEmpty())
                    <div class="div-text-empty">В этой категории пока нет товаров.</div>
                @else
                    <div class="products-block row row-flex">
                        @foreach ($products as $product)
                            @include('partials.product-card', ['product' => $product])
                        @endforeach
                    </div>

                    <div class="pagination-wrap">
                        {{ $products->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
