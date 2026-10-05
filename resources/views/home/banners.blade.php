{{-- Баннеры наверху главной. На старом сайте — Revolution Slider, но каждый
     слайд был одной картинкой со ссылкой: хватает owl.carousel из темы. --}}
@if ($sliderBanners->isNotEmpty())
    <div class="home-slider owl-carousel">
        @foreach ($sliderBanners as $banner)
            @if ($banner->href())
                <a href="{{ $banner->href() }}"><img src="{{ $banner->imageUrl() }}" alt="{{ $banner->title }}" class="img-responsive" width="960" height="350" @unless ($loop->first) loading="lazy" @endunless /></a>
            @else
                <img src="{{ $banner->imageUrl() }}" alt="{{ $banner->title }}" class="img-responsive" width="960" height="350" @unless ($loop->first) loading="lazy" @endunless />
            @endif
        @endforeach
    </div>
@endif

@foreach ($stripBanners as $banner)
    <div class="home-strip">
        @if ($banner->href())
            <a href="{{ $banner->href() }}"><img src="{{ $banner->imageUrl() }}" alt="{{ $banner->title }}" class="img-responsive" width="960" height="132" loading="lazy" /></a>
        @else
            <img src="{{ $banner->imageUrl() }}" alt="{{ $banner->title }}" class="img-responsive" width="960" height="132" loading="lazy" />
        @endif
    </div>
@endforeach

@push('styles')
    <style>
        /* Скругление — из пользовательского CSS темы (.tp-caption img { border-radius: 15px }),
           написанного под разметку Revolution Slider. */
        .home-slider img, .home-strip img { width: 100%; height: auto; border-radius: 15px; }
        .home-strip { margin-top: 20px; }
    </style>
@endpush

@if ($sliderBanners->count() > 1)
    @push('scripts')
        <script>
            $('.home-slider').owlCarousel({items: 1, loop: true, autoplay: true, autoplayTimeout: 6000, autoplayHoverPause: true, nav: false, dots: false, animateOut: 'fadeOut'});
        </script>
    @endpush
@endif
