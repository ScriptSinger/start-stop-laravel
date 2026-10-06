/*
 * Слайдер баннеров на главной: на старом сайте — Revolution Slider, но каждый
 * слайд был одной картинкой, поэтому хватает owl.carousel темы.
 */
import $ from 'jquery';

export function initBannerSliders(root = document) {
    $(root).find('[data-banner-slider]').owlCarousel({
        items: 1, loop: true, autoplay: true, autoplayTimeout: 6000, autoplayHoverPause: true,
        nav: false, dots: false, animateOut: 'fadeOut',
    });
}
