@extends('layouts.app')

@section('body_class', 'menu-expanded')

@section('content')
    <div class="container account-layout">
        <div class="breadcrumb-h1 col-md-offset-3 col-lg-offset-3 col-xxl-offset-4">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li>Избранное</li>
            </ul>
            <h1>Избранное</h1>
        </div>
        <div class="row">
            <aside id="column-left" class="col-sm-4 col-md-3 col-lg-3 col-xxl-4 hidden-xs hidden-sm"></aside>
            <div id="content" class="col-sm-12 col-md-9 col-lg-9 col-xxl-16">
                <div class="account-content">
                @if ($products->isEmpty())
                    <div class="div-text-empty">В избранном пока нет товаров.</div>
                @else
                    <div class="products-block row row-flex">
                        @foreach ($products as $product)
                            <div class="product-layout product-grid grid-view col-sm-6 col-md-4 col-lg-3 col-xxl-5">
                                @include('partials.product-card', ['product' => $product])
                                <form method="post" action="{{ route('wishlist.destroy', $product) }}" class="wishlist__remove text-center">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-link btn-sm"><i class="far fa-trash-alt"></i> Удалить из избранного</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
                </div>
            </div>
        </div>
    </div>
@endsection
