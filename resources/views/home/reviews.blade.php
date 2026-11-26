@if ($storeReviews->isNotEmpty())
<section class="reviews-widget scroll-carousel" aria-labelledby="reviews-widget-title" x-data="scrollCarousel({items: {0: 1, 600: 2, 1000: 3}})">
    <div class="reviews-widget__heading">
        <div>
            <h2 id="reviews-widget-title">Отзывы о магазине</h2>
        </div>
        <div class="reviews-widget__controls" x-cloak>
            <span class="reviews-widget__position">Показано: {{ $storeReviews->count() }}</span>
            <button type="button" class="reviews-widget__arrow" aria-label="Предыдущие отзывы" @click="prev()" :disabled="!canPrev"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
            <button type="button" class="reviews-widget__arrow" aria-label="Следующие отзывы" @click="next()" :disabled="!canNext"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
        </div>
    </div>
    <div class="scroll-carousel__track reviews-widget__track" x-ref="track" tabindex="0" aria-label="Отзывы покупателей, прокручиваемый список">
        @foreach ($storeReviews as $review)
            @php($reviewPhotos = $review->photoDisplayUrls())
            @php($reviewPreviews = $review->photoPreviewUrls())
            <article class="reviews-widget__card">
                <div class="reviews-widget__card-top">
                    <span class="reviews-widget__avatar" aria-hidden="true">
                        @if ($review->avatarDisplayUrl())
                            <img src="{{ $review->avatarDisplayUrl() }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.hidden=true;this.nextElementSibling.hidden=false">
                            <i class="far fa-user" hidden></i>
                        @else
                            <i class="far fa-user"></i>
                        @endif
                    </span>
                    <div><strong>{{ $review->author }}</strong>@if ($review->reviewed_at)<span class="reviews-widget__demo">{{ $review->reviewed_at->format('d.m.Y') }}</span>@endif</div>
                    <span class="reviews-widget__source" aria-label="Источник: {{ $review->source->toString() }}">
                        @if ($review->source === \App\Enums\StoreReviewSource::Avito)
                            <svg viewBox="0 0 30 30" aria-hidden="true"><circle cx="10.595" cy="5.225" fill="#965eeb" r="3.325"/><circle cx="22.245" cy="7.235" fill="#00aaff" r="7.235"/><circle cx="8.9" cy="18.6" fill="#04e061" r="8.9"/><circle cx="24.325" cy="21.005" fill="#ff4053" r="5.375"/></svg>
                        @elseif ($review->source === \App\Enums\StoreReviewSource::Yandex)
                            <span class="reviews-widget__yandex-icon" aria-hidden="true">Я</span>
                        @endif
                        <span>{{ $review->source->toString() }}</span>
                    </span>
                </div>
                <div class="reviews-widget__stars" aria-label="Оценка: {{ $review->rating }} из 5">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</div>
                    <div class="reviews-widget__content" x-data="{truncated: false, observer: null, selectedPhoto: 0, init() { this.$nextTick(() => { this.observer = new ResizeObserver(() => { this.truncated = this.$refs.preview.scrollHeight > this.$refs.preview.clientHeight + 1 }); this.observer.observe(this.$refs.preview) }) }, destroy() { this.observer?.disconnect() }}">
                        <p x-ref="preview" :class="'reviews-widget__preview'">{{ $review->body }}</p>
                        @if (! empty($reviewPhotos))
                            <div class="reviews-widget__photos">
                                @foreach (array_slice($reviewPreviews, 0, 2) as $photo)
                                    <button type="button" class="reviews-widget__photo-button" aria-label="Посмотреть фото {{ $loop->iteration }} к отзыву {{ $review->author }}" aria-haspopup="dialog" @click="selectedPhoto = {{ $loop->index }}; $refs.reviewDialog.showModal()"><img src="{{ $photo }}" alt="" loading="lazy" referrerpolicy="no-referrer"></button>
                                @endforeach
                            </div>
                        @endif
                        <button class="reviews-widget__expand" type="button" x-cloak x-show="truncated || {{ ! empty($reviewPhotos) ? 'true' : 'false' }}" @click="$refs.reviewDialog.showModal()" aria-haspopup="dialog" x-text="truncated ? 'Читать полностью' : 'Смотреть фото'"></button>
                        <dialog x-ref="reviewDialog" class="store-dialog reviews-widget__dialog" aria-label="Полный отзыв — {{ $review->author }}" @click.self="$el.close()">
                            <div class="store-dialog__header">
                                <h3 class="store-dialog__title">Отзыв покупателя</h3>
                                <form method="dialog"><button class="store-dialog__close" aria-label="Закрыть отзыв"><i class="fas fa-times" aria-hidden="true"></i></button></form>
                            </div>
                            <div class="store-dialog__body">
                                <strong>{{ $review->author }}</strong>
                                <p class="reviews-widget__dialog-source">{{ $review->source->toString() }}@if ($review->reviewed_at) · {{ $review->reviewed_at->format('d.m.Y') }}@endif</p>
                                <div class="reviews-widget__stars" aria-label="Оценка: {{ $review->rating }} из 5">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</div>
                                <p>{{ $review->body }}</p>
                                @if (! empty($reviewPhotos))
                                    <div class="reviews-widget__gallery" aria-label="Фотографии к отзыву">
                                        @foreach ($reviewPhotos as $photo)
                                            <img x-show="selectedPhoto === {{ $loop->index }}" src="{{ $photo }}" alt="Фото {{ $loop->iteration }} к отзыву {{ $review->author }}" loading="lazy" referrerpolicy="no-referrer">
                                            <a class="reviews-widget__full-photo" x-show="selectedPhoto === {{ $loop->index }}" href="{{ $photo }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">Открыть фото в полном размере</a>
                                        @endforeach
                                        @if (count($reviewPhotos) > 1)
                                            <div class="reviews-widget__gallery-controls">
                                                <button type="button" @click="selectedPhoto = (selectedPhoto + {{ count($reviewPhotos) }} - 1) % {{ count($reviewPhotos) }}" aria-label="Предыдущее фото">←</button>
                                                <span x-text="(selectedPhoto + 1) + ' / {{ count($reviewPhotos) }}'"></span>
                                                <button type="button" @click="selectedPhoto = (selectedPhoto + 1) % {{ count($reviewPhotos) }}" aria-label="Следующее фото">→</button>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </dialog>
                    </div>
            </article>
        @endforeach
    </div>
    <a class="reviews-widget__link" href="https://yandex.ru/maps/org/start_stop/164124425812/reviews/" target="_blank" rel="noopener noreferrer">Читать отзывы на Яндекс Картах <span aria-hidden="true">→</span></a>
</section>
@endif
