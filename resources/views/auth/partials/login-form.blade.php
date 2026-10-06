{{-- Форма входа: на странице /login и в окне «Авторизация» (шапка, оформление
     заказа). В окне отправляется без перезагрузки, после входа страница обновляется. --}}
<form method="post" action="{{ route('login.store') }}" class="modal-login__form" x-data="ajaxForm({reload: true})" @submit="submit">
    @csrf
    <div class="help-block text-danger" x-show="error('_form')" x-text="error('_form')" x-cloak></div>
    <x-form-group name="email" label="E-Mail адрес" for="login-email-{{ $formId ?? 'page' }}">
        <input type="email" name="email" id="login-email-{{ $formId ?? 'page' }}" value="{{ old('email') }}" placeholder="E-Mail адрес" class="form-control" autocomplete="email" required />
    </x-form-group>
    <x-form-group name="password" label="Пароль" for="login-password-{{ $formId ?? 'page' }}">
        <input type="password" name="password" id="login-password-{{ $formId ?? 'page' }}" placeholder="Пароль" class="form-control" autocomplete="current-password" required />
    </x-form-group>
    <div class="checkbox">
        <label><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Запомнить меня</label>
    </div>
    <button type="submit" class="modal-login__btn account-login__btn btn btn-lg btn-primary" :disabled="sending">Войти</button>
    <div class="modal-login__links">
        <a href="{{ route('password.request') }}" class="modal-login__link">Забыли пароль?</a>
        <a href="{{ route('register') }}" class="modal-login__link">Регистрация</a>
    </div>
</form>
