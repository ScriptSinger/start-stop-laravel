{{-- Блок ошибки темы UniShop2 (error/not_found.twig): круг с кодом, текст
     и кнопка дальше — на главную. --}}
<div class="error-not-found uni-wrapper">
    <div class="error-not-found__404">{{ $code }}</div>
    <p>{{ $message }}</p>
    <div class="error-not-found__actions">
        <a href="{{ $actionUrl ?? url('/') }}" class="btn btn-primary">{{ $actionText ?? 'На главную' }}</a>
    </div>
</div>
