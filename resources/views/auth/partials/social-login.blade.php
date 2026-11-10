@if ((filled(config('services.yandex.client_id')) && filled(config('services.yandex.client_secret')) && filled(config('services.yandex.redirect'))) || (filled(config('services.vkid.client_id')) && filled(config('services.vkid.redirect'))))
    <div @class(['auth-social', 'auth-social--registration' => $registration])>
        <div class="auth-social__title"><span>{{ $registration ? 'Или зарегистрироваться через' : 'Или войти через' }}</span></div>
        <div class="auth-social__providers">
            @if (filled(config('services.yandex.client_id')) && filled(config('services.yandex.client_secret')) && filled(config('services.yandex.redirect')))
                <a href="{{ route('auth.yandex') }}" class="auth-social__provider">
                    <span class="auth-social__provider-icon" aria-hidden="true">Я</span>
                    <span>Яндекс ID</span>
                </a>
            @endif
            @if (filled(config('services.vkid.client_id')) && filled(config('services.vkid.redirect')))
                <a href="{{ route('auth.vkid') }}" class="auth-social__provider">
                    <span class="auth-social__provider-icon auth-social__provider-icon--vk" aria-hidden="true">VK</span>
                    <span>VK ID</span>
                </a>
            @endif
        </div>
        <p class="auth-social__note">Продолжая, вы соглашаетесь с <a href="{{ route('page.show', config('shop.checkout_agreement_page')) }}" target="_blank" rel="noopener">политикой безопасности</a>.</p>
    </div>
@endif
