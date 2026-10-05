@php($image = $product->image ? Illuminate\Support\Facades\Storage::disk('public')->url($product->image) : Illuminate\Support\Facades\Storage::disk('public')->url('no_image.png'))

<div class="product-layout product-grid grid-view col-sm-6 col-md-4 col-lg-3">
    <div class="product-thumb uni-item">
        <div class="product-thumb__image">
            <a href="{{ route('product.show', $product) }}" title="{{ $product->name }}">
                <img src="{{ $image }}" alt="{{ $product->name }}" class="img-responsive" loading="lazy" />
            </a>
        </div>
        <div class="product-thumb__caption">
            <a class="product-thumb__name" href="{{ route('product.show', $product) }}">{{ $product->name }}</a>

            <div class="product-thumb__price price">
                {{ number_format((float) $product->price, 0, ',', ' ') }} р.
            </div>

            {{-- Кнопка визуальная — корзина не подключена, это Фаза 5 плана миграции. --}}
            <div class="product-thumb__cart cart">
                <button type="button" class="product-thumb__add-to-cart btn btn-primary" title="В корзину">
                    <i class="fas fa-shopping-cart"></i><span>В корзину</span>
                </button>
            </div>
        </div>
    </div>
</div>
