{{-- Список товаров с «Показать еще», пагинацией и подписью «Показано с … по …»
     (каталог, поиск, подбор АКБ). Ожидает $products (paginator) и $columnClass. --}}
<div data-product-grid data-next-url="{{ $products->nextPageUrl() }}" x-data="showMore(@js($products->nextPageUrl()))">
    <div class="products-block row row-flex" x-ref="products">
        @foreach ($products as $product)
            <div class="product-layout product-grid grid-view {{ $columnClass }}">
                @include('partials.product-card', ['product' => $product])
            </div>
        @endforeach
    </div>

    @if ($products->hasMorePages())
        <div class="show-more" x-show="nextUrl">
            <button type="button" class="show-more__btn btn btn-xl btn-default" @click="load"><i class="show-more__icon fas fa-sync-alt" :class="{spin: loading}"></i><span>Показать еще</span></button>
        </div>
    @endif
    <div x-ref="pagination">{{ $products->links() }}</div>
    <div class="pagination-text" x-ref="paginationText">Показано с {{ $products->firstItem() }} по {{ $products->lastItem() }} из {{ $products->total() }} (всего {{ $products->lastPage() }} <x-plural :count="$products->lastPage()" forms="страница|страницы|страниц" />)</div>
</div>
