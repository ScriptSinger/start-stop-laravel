{{-- Баннеры наверху главной. На старом сайте — Revolution Slider, но каждый
     слайд был одной картинкой со ссылкой: хватает owl.carousel из темы. --}}
@if ($sliderBanners->isNotEmpty())
    <div class="home-slider owl-carousel" @if ($sliderBanners->count() > 1) data-banner-slider @endif>
        @foreach ($sliderBanners as $banner)
            @if ($banner->href())
                <a href="{{ $banner->href() }}"><img src="{{ $banner->imageUrl() }}" alt="{{ $banner->label() }}" class="img-responsive" width="960" height="350" @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif /></a>
            @else
                <img src="{{ $banner->imageUrl() }}" alt="{{ $banner->label() }}" class="img-responsive" width="960" height="350" @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif />
            @endif
        @endforeach
    </div>
@endif

@foreach ($stripBanners as $banner)
    <div class="home-strip">
        @if ($banner->href())
            <a href="{{ $banner->href() }}"><img src="{{ $banner->imageUrl() }}" alt="{{ $banner->label() }}" class="img-responsive" width="960" height="132" loading="lazy" /></a>
        @else
            <img src="{{ $banner->imageUrl() }}" alt="{{ $banner->label() }}" class="img-responsive" width="960" height="132" loading="lazy" />
        @endif
    </div>
@endforeach
