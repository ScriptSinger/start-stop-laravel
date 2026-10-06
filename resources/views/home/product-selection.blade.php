{{-- Подборка товаров (модуль uni_five_in_one_v2 старого сайта): разметка 1:1. --}}
@if ($products->isNotEmpty())
    @php($moduleClass = 'five-in-one-'.$selection->value)
    @php($link = $selection->headingLink())
    <div class="heading">{{ $selection->toString() }}@if ($link) <a href="{{ route('category.show', $link['category']) }}" class="heading__link">{{ $link['title'] }}</a>@endif</div>
    <div class="tab-content">
        <div class="tab-pane active">
            <div class="uni-module {{ $moduleClass }}" data-uni-module="carousel">
                <div class="uni-module__wrapper">
                    @foreach ($products as $product)
                        @include('partials.product-card', ['product' => $product])
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif
