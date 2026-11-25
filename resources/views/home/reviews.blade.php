@php
    $demoReviews = [
        ['author' => 'Имя автора 1', 'source' => 'Яндекс Карты', 'text' => 'Помогли подобрать аккумулятор и ответили на вопросы по установке.'],
        ['author' => 'Имя автора 2', 'source' => 'Авито', 'text' => 'Заказ оформлен удобно, менеджер уточнил детали доставки.'],
        ['author' => 'Имя автора 3', 'source' => 'Яндекс Карты', 'text' => 'Проконсультировали по характеристикам и условиям гарантии. Рассказали о вариантах для автомобиля, помогли сравнить модели и объяснили, как правильно ухаживать за аккумулятором. Ответили на вопросы о сроке службы, установке и выборе ёмкости. Также рассказали, как работает обмен старого аккумулятора при покупке нового.'],
        ['author' => 'Имя автора 4', 'source' => 'Авито', 'text' => 'Быстро нашёл подходящий вариант для своего автомобиля.'],
        ['author' => 'Имя автора 5', 'source' => 'Яндекс Карты', 'text' => 'Рассказали об обмене старого аккумулятора при покупке нового.'],
    ];
@endphp
<section class="reviews-widget scroll-carousel" aria-labelledby="reviews-widget-title" x-data="scrollCarousel({items: {0: 1, 600: 2, 1000: 3}})">
    <div class="reviews-widget__heading">
        <div>
            <h2 id="reviews-widget-title">Отзывы о магазине</h2>
            <p class="reviews-widget__notice">Пример оформления: отзывы пока тестовые.</p>
        </div>
        <div class="reviews-widget__controls" x-cloak>
            <button type="button" class="reviews-widget__arrow" aria-label="Предыдущие отзывы" @click="prev()" :disabled="!canPrev"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
            <button type="button" class="reviews-widget__arrow" aria-label="Следующие отзывы" @click="next()" :disabled="!canNext"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
        </div>
    </div>
    <div class="scroll-carousel__track reviews-widget__track" x-ref="track" tabindex="0" aria-label="Тестовые отзывы, прокручиваемый список">
        @foreach ($demoReviews as $review)
            <article class="reviews-widget__card">
                <div class="reviews-widget__card-top">
                    <span class="reviews-widget__avatar" aria-hidden="true"><i class="far fa-user"></i></span>
                    <div><strong>{{ $review['author'] }}</strong><span class="reviews-widget__demo">Тестовые данные</span></div>
                    <span class="reviews-widget__source" aria-label="Пример источника: {{ $review['source'] }}">
                        @if ($review['source'] === 'Авито')
                            <svg viewBox="0 0 30 30" aria-hidden="true"><circle cx="10.595" cy="5.225" fill="#965eeb" r="3.325"/><circle cx="22.245" cy="7.235" fill="#00aaff" r="7.235"/><circle cx="8.9" cy="18.6" fill="#04e061" r="8.9"/><circle cx="24.325" cy="21.005" fill="#ff4053" r="5.375"/></svg>
                        @else
                            <span class="reviews-widget__yandex-icon" aria-hidden="true">Я</span>
                        @endif
                        <span>{{ $review['source'] }}</span>
                    </span>
                </div>
                <div class="reviews-widget__stars" aria-label="Пример оценки: 5 из 5">★★★★★</div>
                    <div x-data="{truncated: false, observer: null, init() { this.$nextTick(() => { this.observer = new ResizeObserver(() => { this.truncated = this.$refs.preview.scrollHeight > this.$refs.preview.clientHeight + 1 }); this.observer.observe(this.$refs.preview) }) }, destroy() { this.observer?.disconnect() }}">
                        <p x-ref="preview" :class="'reviews-widget__preview'">{{ $review['text'] }}</p>
                        <button class="reviews-widget__expand" type="button" x-cloak x-show="truncated" @click="$el.nextElementSibling.showModal()" aria-haspopup="dialog">Читать полностью</button>
                        <dialog class="store-dialog reviews-widget__dialog" aria-label="Полный отзыв — {{ $review['author'] }}" @click.self="$el.close()">
                            <div class="store-dialog__header">
                                <h3 class="store-dialog__title">Отзыв покупателя</h3>
                                <form method="dialog"><button class="store-dialog__close" aria-label="Закрыть отзыв"><i class="fas fa-times" aria-hidden="true"></i></button></form>
                            </div>
                            <div class="store-dialog__body">
                                <strong>{{ $review['author'] }}</strong>
                                <p class="reviews-widget__dialog-source">{{ $review['source'] }} · Тестовые данные</p>
                                <div class="reviews-widget__stars" aria-label="Пример оценки: 5 из 5">★★★★★</div>
                                <p>{{ $review['text'] }}</p>
                            </div>
                        </dialog>
                    </div>
            </article>
        @endforeach
    </div>
    <a class="reviews-widget__link" href="https://yandex.ru/maps/org/start_stop/164124425812/reviews/" target="_blank" rel="noopener noreferrer">Читать отзывы на Яндекс Картах <span aria-hidden="true">→</span></a>
</section>
