{{-- Плашки разделов и популярных марок в конце гайда: [[block:catalog]] в тексте (App\Services\Catalog\GuideLinks). --}}
@if ($categories !== [] || $brands !== [])
    <div class="guide-catalog">
        @if ($categories !== [])
            <ul class="guide-catalog__list">
                @foreach ($categories as $category)
                    <li><a class="guide-catalog__item guide-catalog__item_main" href="{{ $category['url'] }}">{{ $category['name'] }}</a></li>
                @endforeach
            </ul>
        @endif
        @if ($brands !== [])
            <div class="guide-catalog__title">Аккумуляторы по маркам автомобилей</div>
            <ul class="guide-catalog__list">
                @foreach ($brands as $brand)
                    <li><a class="guide-catalog__item" href="{{ $brand['url'] }}">Аккумуляторы для {{ $brand['name'] }}</a></li>
                @endforeach
                <li><a class="guide-catalog__item" href="{{ route('car-landing.index') }}">Все марки</a></li>
            </ul>
        @endif
    </div>
@endif
