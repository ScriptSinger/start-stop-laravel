{{-- Разметка модуля uni_notification старого сайта. Показывается, пока нет cookie notificationOffTime. --}}
<div id="uni-notification" class="notification fixed hidden" x-data="cookieNotice({rememberHours: {{ (int) config('shop.cookie_notice.remember_hours') }}})" :class="{hidden: !visible}">
    <div class="container">
        <div class="notification__wrapper fixed">
            <div class="notification__text fixed"><p>{{ config('shop.cookie_notice.text') }}</p></div>
            <div class="notification__buttons">
                <button type="button" class="notification__button btn btn-sm btn-primary" @click="accept">{{ config('shop.cookie_notice.button') }}</button>
            </div>
        </div>
    </div>
</div>
