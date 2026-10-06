{{-- Подборка товаров (модуль uni_five_in_one_v2 старого сайта): разметка 1:1. --}}
@if ($products->isNotEmpty())
    @php($moduleClass = 'five-in-one-'.$selection->value)
    @php($link = $selection->headingLink())
    <div class="heading">{{ $selection->toString() }}@if ($link) <a href="{{ route('category.show', $link['category']) }}" class="heading__link">{{ $link['title'] }}</a>@endif</div>
    <div class="tab-content">
        <div class="tab-pane active">
            {{-- Тема на широких экранах ставит 5 карточек в ряд; у нас — 4,
                 как в «Популярных категориях» над подборкой. --}}
            <div class="uni-module {{ $moduleClass }}" data-uni-module="carousel" data-uni-module-items='{"0":{"items":2},"700":{"items":3},"992":{"items":4}}'>
                <div class="uni-module__wrapper">
                    @foreach ($products as $product)
                        @include('partials.product-card', ['product' => $product])
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif
