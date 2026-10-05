{{-- Модуль html «Отзывы» старого сайта (oc_module 49): виджет MyReviews. --}}
<div class="html-module">
    <div style="display: flex; justify-content: center;margin-top: 20px;border-radius: 20px;">
        <iframe style="width: 100%;height: 100%;max-width: 1170px;border: none;outline: none;padding: 0;margin: 0" id="myReviews__block-widget" title="Отзывы о магазине"></iframe>
    </div>
</div>

@push('scripts')
    <script src="{{ config('shop.reviews_widget.script') }}" defer></script>
    <script defer>
        (function () {
            const init = () => new window.myReviews.BlockWidget({
                uuid: @json(config('shop.reviews_widget.uuid')),
                name: @json(config('shop.reviews_widget.name')),
                additionalFrame: 'none',
                lang: 'ru',
                widgetId: '0',
            }).init();

            // Скрипт виджета подключён с defer — ждём его, как на старом сайте.
            window.addEventListener('load', () => window.myReviews && init());
        })();
    </script>
@endpush
