{{-- Баннеры наверху главной. На старом сайте — Revolution Slider, но каждый
     слайд был одной картинкой со ссылкой: хватает карусели на scroll-snap.
     Без JavaScript она листается свайпом, с ним — ещё и сама, раз в 6 секунд. --}}
@if ($sliderBanners->isNotEmpty())
    <div class="home-slider scroll-carousel" @if ($sliderBanners->count() > 1) x-data="scrollCarousel({autoplay: 6000})" @mouseenter="paused = true" @mouseleave="paused = false" @focusin="paused = true" @focusout="paused = false" @endif>
        <div class="scroll-carousel__track" @if ($sliderBanners->count() > 1) x-ref="track" @endif>
            @foreach ($sliderBanners as $banner)
                @if ($banner->href())
                    <a href="{{ $banner->href() }}"><img src="{{ $banner->imageUrl() }}" alt="{{ $banner->label() }}" class="img-responsive" width="960" height="350" @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif /></a>
                @else
                    <img src="{{ $banner->imageUrl() }}" alt="{{ $banner->label() }}" class="img-responsive" width="960" height="350" @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif />
                @endif
            @endforeach
        </div>
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
