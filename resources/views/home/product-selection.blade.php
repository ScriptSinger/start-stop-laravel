{{-- Подборка товаров (модуль uni_five_in_one_v2 старого сайта): разметка 1:1. --}}
@if ($products->isNotEmpty())
    @php($moduleClass = 'five-in-one-'.$selection->value)
    @php($link = $selection->headingLink())
    <div class="heading section-heading">
        <span class="section-heading__eyebrow">{{ $selection->eyebrow() }}</span>
        <span class="section-heading__title">{{ $selection->toString() }}@if ($link) <a href="{{ route('category.show', $link['category']) }}" class="heading__link">{{ $link['title'] }}</a>@endif</span>
    </div>
    <div class="tab-content">
        <div class="tab-pane active">
            {{-- Тема на широких экранах ставит 5 карточек в ряд; у нас — 4, как в
                 «Популярных категориях» над подборкой. Если товаров меньше четырёх,
                 колонок столько же, сколько карточек: ряд занимает всю ширину. --}}
            <div @class(['uni-module home-product-selection', $moduleClass, 'home-product-selection--'.$products->count() => $products->count() < 4]) data-home-selection>
                <div class="uni-module__wrapper">
                    @foreach ($products as $product)
                        @include('partials.product-card', ['product' => $product])
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif
