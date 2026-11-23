@extends('layouts.app')

@section('content')
    <div class="container account-layout">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li>Избранное</li>
            </ul>
            <h1>Избранное</h1>
        </div>
        <div class="row">
            <div id="content" class="col-sm-12">
                <div>
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
