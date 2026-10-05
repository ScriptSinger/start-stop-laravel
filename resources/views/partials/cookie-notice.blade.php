{{-- Разметка модуля uni_notification старого сайта. Показывается, пока нет cookie notificationOffTime. --}}
<div id="uni-notification" class="notification fixed hidden" data-remember-hours="{{ config('shop.cookie_notice.remember_hours') }}">
    <div class="container">
        <div class="notification__wrapper fixed">
            <div class="notification__text fixed"><p>{{ config('shop.cookie_notice.text') }}</p></div>
            <div class="notification__buttons">
                <button type="button" class="notification__button btn btn-sm btn-primary">{{ config('shop.cookie_notice.button') }}</button>
            </div>
        </div>
    </div>
</div>
