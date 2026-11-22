{{-- Контакты: призыв позвонить, адрес, режим работы и карта одним блоком (на главной
     и на странице «О компании»). Раньше — отдельные блоки «Обратный звонок» и «Новая карта»
     старого сайта (oc_module 60 и 46); контакты берутся из config/shop.php. --}}
<section class="home-contacts" aria-labelledby="home-contacts-title">
    <div class="home-contacts__info">
        <span class="section-heading__eyebrow">Контакты</span>
        <h2 class="home-contacts__title" id="home-contacts-title">Не знаете, какой аккумулятор выбрать?</h2>
        <p class="home-contacts__lead">Позвоните или оставьте номер — консультант подберёт аккумулятор на ваш автомобиль.</p>
        <div class="home-contacts__actions">
            <a class="btn btn-primary home-contacts__button" href="tel:+{{ preg_replace('/\D/', '', config('shop.phone')) }}"><i class="fas fa-phone" aria-hidden="true"></i> {{ config('shop.phone') }}</a>
            <a class="home-contacts__link" href="{{ route('callback.create') }}" @click.prevent="$store.modal.open($el.href, 'Заказать звонок')">Заказать обратный звонок</a>
        </div>
        <ul class="home-contacts__list">
            <li><i class="fas fa-map-marker-alt fa-fw" aria-hidden="true"></i><span>{{ config('shop.address') }}</span></li>
            @foreach (config('shop.opening_hours') as $hours)
                <li><i class="far fa-clock fa-fw" aria-hidden="true"></i><span>{{ $hours['days'] }}: {{ $hours['from'] }}–{{ $hours['to'] }}</span></li>
            @endforeach
            <li><i class="far fa-envelope fa-fw" aria-hidden="true"></i><a href="mailto:{{ config('shop.email') }}">{{ config('shop.email') }}</a></li>
        </ul>
    </div>
    {{-- Интерактивная карта через JavaScript API Яндекса. --}}
    <div class="home-contacts__map" data-yandex-map data-coordinates="{{ json_encode(config('shop.map_coordinates')) }}" data-map-key="{{ config('services.yandex_maps.key') }}"></div>
</section>
