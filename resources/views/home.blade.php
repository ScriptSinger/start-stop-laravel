@extends('layouts.app')

@section('title', $search !== '' ? 'Поиск: '.$search.' — '.config('shop.name') : config('shop.name'))

{{-- Как на старом сайте: меню категорий раскрыто только на главной. --}}
@push('styles')
    <style>@media (min-width:992px) {header .menu1 .menu__collapse {display:block !important}}</style>
@endpush

@push('module-styles')
    @foreach (['home-banner', 'category_wall', 'news'] as $stylesheet)
        <link href="{{ asset("theme/stylesheet/{$stylesheet}.css") }}" rel="stylesheet" media="screen" />
    @endforeach
@endpush

@section('content')
    <div class="container">
        <div class="row">
            @include('partials.category-sidebar')

            <div class="col-sm-8 col-md-9">
                @if ($search === '')
                    {{-- Блок доверия — реальный текст с живого сайта, просто нет своей
                    CMS-таблицы под него ещё, поэтому пока хардкод, не выдумано. --}}
                    <div class="trust-row row" style="margin-bottom: 30px;">
                        <div class="col-sm-4">
                            <i class="fas fa-car-battery" style="color: var(--btn-primary-bg, #c82a00); font-size: 28px;"></i>
                            <strong>Диагностика аккумулятора</strong>
                            <p>Проверим Ваш аккумулятор и при необходимости подберём новый</p>
                        </div>
                        <div class="col-sm-4">
                            <i class="fas fa-car" style="color: var(--btn-primary-bg, #c82a00); font-size: 28px;"></i>
                            <strong>Диагностика автомобиля</strong>
                            <p>Бесплатная проверка генератора, стартера и утечки тока на Вашем автомобиле</p>
                        </div>
                        <div class="col-sm-4">
                            <i class="fas fa-bolt" style="color: var(--btn-primary-bg, #c82a00); font-size: 28px;"></i>
                            <strong>Обслуживание и зарядка</strong>
                            <p>Бесплатная зарядка и обслуживание АКБ в течение гарантийного периода</p>
                        </div>
                    </div>

                    @include('partials.battery-filter')

                    @if ($menuCategories->isNotEmpty())
                        <h2>Популярные категории</h2>
                        <div class="category-wall row" style="margin-bottom: 30px;">
                            @foreach ($menuCategories->take(4) as $tileCategory)
                                <div class="col-sm-3">
                                    <a href="{{ route('category.show', $tileCategory) }}" class="category-wall__item" style="display: block; position: relative; height: 160px; border-radius: 8px; overflow: hidden; color: #fff; text-decoration: none;">
                                        @if ($tileCategory->image)
                                            <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($tileCategory->image) }}" alt="{{ $tileCategory->name }}" style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: 0;" />
                                        @endif
                                        <div style="position: relative; z-index: 1; background: rgba(0,0,0,.45); height: 100%; padding: 15px; display: flex; flex-direction: column; justify-content: flex-end;">
                                            <strong>{{ $tileCategory->name }}</strong>
                                        </div>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif

                <h1>{{ $search !== '' ? 'Результаты поиска: '.$search : 'Каталог' }}</h1>

                @if ($products->isEmpty())
                    <div class="div-text-empty">Ничего не найдено.</div>
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
