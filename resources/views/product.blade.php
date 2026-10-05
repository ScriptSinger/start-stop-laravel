@extends('layouts.app')

@section('title', $product->name.' — '.config('shop.name'))

@section('content')
    <div class="container">
        <ul class="breadcrumb">
            <li><a href="{{ route('home') }}">Главная</a></li>
            @foreach ($product->categories as $category)
                <li><a href="{{ route('category.show', $category) }}">{{ $category->name }}</a></li>
            @endforeach
            <li>{{ $product->name }}</li>
        </ul>

        <div class="row">
            <div class="col-sm-5">
                @php($image = $product->image ? Illuminate\Support\Facades\Storage::disk('public')->url($product->image) : Illuminate\Support\Facades\Storage::disk('public')->url('no_image.png'))
                <img src="{{ $image }}" alt="{{ $product->name }}" class="img-responsive img-thumbnail" />

                @if ($product->images->isNotEmpty())
                    <div class="row" style="margin-top: 10px;">
                        @foreach ($product->images as $extraImage)
                            <div class="col-sm-3">
                                <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($extraImage->path) }}" alt="{{ $product->name }}" class="img-responsive img-thumbnail" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="col-sm-7">
                <h1>{{ $product->name }}</h1>

                @if ($product->code)
                    <div>Код товара: {{ $product->code }}</div>
                @endif

                @if ($product->sku)
                    <div class="product-thumb__model" data-text="Артикул">{{ $product->sku }}</div>
                @endif

                @if ($product->manufacturer)
                    <div>Производитель: {{ $product->manufacturer->name }}</div>
                @endif

                <div class="price" style="font-size: 28px; margin: 15px 0;">
                    {{ number_format($product->displayPrice(), 0, ',', ' ') }} р.
                </div>

                {{-- Кнопка визуальная — корзина не подключена, это Фаза 5 плана миграции. --}}
                @if ($product->isAvailableOnOrder())
                    <div style="margin-bottom: 10px;">Под заказ</div>
                    <button type="button" class="btn btn-primary btn-lg">
                        <i class="fa fa-truck"></i> Заказать
                    </button>
                @else
                    <button type="button" class="btn btn-primary btn-lg">
                        <i class="fas fa-shopping-cart"></i> В корзину
                    </button>
                @endif

                @if ($specifications->isNotEmpty())
                    <h3 style="margin-top: 30px;">Характеристики</h3>
                    <table class="table table-striped">
                        <tbody>
                            @foreach ($specifications as $name => $value)
                                <tr>
                                    <td>{{ $name }}</td>
                                    <td>{{ $value }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                @if ($product->description)
                    <div class="product-page" style="margin-top: 30px;">
                        {!! $product->description !!}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
