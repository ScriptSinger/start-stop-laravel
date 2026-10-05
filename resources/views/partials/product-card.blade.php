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
                {{ number_format($product->displayPrice(), 0, ',', ' ') }} р.
            </div>

            {{-- Из карточки — 1 шт. без трейд-ина; галочка обмена есть в корзине и на странице товара. --}}
            <form method="post" action="{{ route('cart.store', $product) }}" class="product-thumb__cart cart">
                @csrf
                @if ($product->isAvailableOnOrder())
                    <button type="submit" class="product-thumb__add-to-cart btn btn-primary" title="Заказать">
                        <i class="fa fa-truck"></i><span>Заказать</span>
                    </button>
                @else
                    <button type="submit" class="product-thumb__add-to-cart btn btn-primary" title="В корзину">
                        <i class="fas fa-shopping-cart"></i><span>В корзину</span>
                    </button>
                @endif
            </form>
        </div>
    </div>
</div>
