{{-- Разметка — 1:1 с footer.twig темы UniShop2 старого сайта. --}}
@php($phoneHref = 'tel:+'.preg_replace('/\D/', '', config('shop.phone')))

<footer class="footer">
    <div class="container">
        <div class="row row-flex">
            @foreach ($footerColumns as $column)
                <div class="footer__column col-sm-6 col-md-3">
                    <div class="footer__column-heading" data-toggle="collapse" data-target=".footer__column-ul-{{ $loop->iteration }}" onclick="$(this).toggleClass('open')">
                        {{ $column->title }} <i class="fas fa-chevron-down visible-xs"></i>
                    </div>
                    <ul class="footer__column-ul footer__column-ul-{{ $loop->iteration }} collapse list-unstyled">
                        @foreach ($column->children as $link)
                            <li class="footer__column-li"><a href="{{ $link->href() }}" title="{{ $link->title }}" class="footer__column-a">{{ $link->title }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <div class="footer__column col-sm-6 col-md-3">
                <div class="footer__column-heading footer__column-heading-addr">Наши контакты</div>
                <ul class="footer__column-ul footer__contacts list-unstyled">
                    <li class="footer__column-li footer__contacts-li">
                        <i class="footer__contacts-icon fa-fw fas fa-phone"></i>
                        <a class="footer__column-a" href="{{ $phoneHref }}">{{ config('shop.phone') }}</a>
                    </li>
                    <li class="footer__column-li footer__contacts-li"><i class="footer__contacts-icon fa fa-envelope fa-fw"></i><a class="footer__column-a" href="mailto:{{ config('shop.email') }}">{{ config('shop.email') }}</a></li>
                </ul>
                <div class="footer__column-heading footer__column-heading-addr">Наш адрес</div>
                <ul class="footer__column-ul footer__contacts list-unstyled">
                    <li class="footer__column-li footer__contacts-li"><i class="footer__contacts-icon fa fa-map-marker fa-fw"></i><a class="footer__column-a" href="{{ route('contact') }}">{{ config('shop.address') }}</a></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="footer__socials-payments">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-6">
                    <div class="footer__media">
                        @foreach (config('shop.socials') as $social)
                            <i class="footer__socials-icon uni-href {{ $social['icon'] }}" data-href="{{ $social['url'] }}" data-target="_blank"></i>
                        @endforeach
                    </div>
                </div>
                <div class="col-sm-12 col-md-6">
                    <div class="visible-xs visible-sm" style="height:15px"></div>
                    <div class="footer__payments"></div>
                </div>
            </div>
        </div>
    </div>
</footer>
{{-- Кнопка «наверх» (fly-block темы; закладки, сравнение и контакты в нём
     на старом сайте были выключены). --}}
<div class="fly-block">
    <div class="fly-block__item fly-block__scrollup" title="Наверх" data-scroll-top>
        <i class="fa fa-chevron-up fly-block__scrollup-icon" aria-hidden="true"></i>
    </div>
</div>
