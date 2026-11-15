{{-- Баннеры наверху главной. На старом сайте — Revolution Slider, но каждый
     слайд был одной картинкой со ссылкой: хватает owl.carousel из темы. --}}
@if ($sliderBanners->isNotEmpty())
    <div class="home-slider owl-carousel" @if ($sliderBanners->count() > 1) data-banner-slider @endif>
        @foreach ($sliderBanners as $banner)
            @if ($banner->href())
                @php($bannerLabel = trim($banner->title ?? '') ?: match (parse_url($banner->href(), PHP_URL_PATH)) { '/page/trade-in' => 'Условия трейд-ин', '/page/dostavka-i-oplata' => 'Доставка и оплата', '/category/akkumulyatori' => 'Каталог аккумуляторов', default => 'Подробнее о предложении' })
                <a href="{{ $banner->href() }}" aria-label="{{ $bannerLabel }}"><img src="{{ $banner->imageUrl() }}" alt="" class="img-responsive" width="960" height="350" @unless ($loop->first) loading="lazy" @endunless /></a>
            @else
                <img src="{{ $banner->imageUrl() }}" alt="{{ $banner->title }}" class="img-responsive" width="960" height="350" @unless ($loop->first) loading="lazy" @endunless />
            @endif
        @endforeach
    </div>
@endif

@foreach ($stripBanners as $banner)
    <div class="home-strip">
        @if ($banner->href())
            @php($bannerLabel = trim($banner->title ?? '') ?: match (parse_url($banner->href(), PHP_URL_PATH)) { '/page/trade-in' => 'Условия трейд-ин', '/page/dostavka-i-oplata' => 'Доставка и оплата', '/category/akkumulyatori' => 'Каталог аккумуляторов', default => 'Подробнее о предложении' })
            <a href="{{ $banner->href() }}" aria-label="{{ $bannerLabel }}"><img src="{{ $banner->imageUrl() }}" alt="" class="img-responsive" width="960" height="132" loading="lazy" /></a>
        @else
            <img src="{{ $banner->imageUrl() }}" alt="{{ $banner->title }}" class="img-responsive" width="960" height="132" loading="lazy" />
        @endif
    </div>
@endforeach
