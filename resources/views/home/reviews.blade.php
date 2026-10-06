{{-- Модуль html «Отзывы» старого сайта (oc_module 49): виджет MyReviews,
     скрипт подключает resources/js/storefront/embeds.js. --}}
<div class="html-module">
    <div class="reviews-widget">
        <iframe class="reviews-widget__frame" id="myReviews__block-widget" title="Отзывы о магазине"
                data-reviews-widget data-script="{{ config('shop.reviews_widget.script') }}" data-uuid="{{ config('shop.reviews_widget.uuid') }}" data-name="{{ config('shop.reviews_widget.name') }}"></iframe>
    </div>
</div>
