@extends('layouts.app')

{{-- Разметка — 1:1 с product/category.twig темы UniShop2 старого сайта. --}}
@section('content')
    <div id="product-category" class="container">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                @if ($category->parent)
                    <li><a href="{{ route('category.show', $category->parent) }}">{{ $category->parent->name }}</a></li>
                @endif
                @if ($manufacturer)
                    <li><a href="{{ route('category.show', $category) }}">{{ $category->name }}</a></li>
                    <li>{{ $manufacturer->name }}</li>
                @else
                    <li>{{ $category->name }}</li>
                @endif
            </ul>
            <h1>{{ $manufacturer ? ($brandPage?->heading ?: $category->name.' '.$manufacturer->name) : ($category->heading ?: $category->name) }}</h1>
        </div>
        <div class="row">
            <aside id="column-left" class="col-sm-4 col-md-3 col-lg-3 col-xxl-4" x-data="catalogFilter({countUrl: @js(route('category.count', $category)), priceMin: {{ $priceMin }}, priceMax: {{ $priceMax }}, brandUrl: @js($manufacturer ? route('category.manufacturer', [$category, $manufacturer]) : null), brandId: @js($manufacturer?->id)})" @keydown.escape.window="close()" @open-catalog-filter.window="show()">
                @include('partials.catalog-filter')
            </aside>
            <div id="content" class="col-sm-8 col-md-9 col-lg-9 col-xxl-16">
                <div class="uni-wrapper">
                    @if ($manufacturer)
                        @if ($brandPage?->hasDescription())
                            <div class="category-info">{!! $brandPage->description !!}</div>
                        @endif
                    @elseif ($category->hasDescription())
                        <div class="category-info">{!! $category->description !!}</div>
                    @endif

                    @if ($guide)
                        <p class="category-guide"><i class="far fa-lightbulb" aria-hidden="true"></i> <a href="{{ route('guide.show', $guide) }}">{{ $guide->title }}</a></p>
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

                    {{-- Панель над списком: на телефоне «Фильтры (N)» и сортировка в одной строке, прилипает к верху при прокрутке. --}}

                    @php($activeFilters = $filter->activeCount(manufacturerFixed: $manufacturer !== null))

                    <div class="catalog-toolbar">

                        <button type="button" class="catalog-toolbar__filters" @click="$dispatch('open-catalog-filter')" aria-controls="catalog-filter"><i class="fas fa-sliders-h" aria-hidden="true"></i> Фильтры @if ($activeFilters > 0)<span class="catalog-toolbar__badge" aria-label="выбрано условий: {{ $activeFilters }}">{{ $activeFilters }}</span>@endif</button>

                        @include('partials.catalog-sorts')

                    </div>

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
            </div>
        </div>
    </div>
@endsection
