{{-- Форма «Заказать звонок»: в окне ($store.modal) и на странице /callback. --}}
<form method="post" action="{{ route('callback.store') }}" class="uni-form" x-data="ajaxForm" @submit="submit">
    @csrf
    <div class="alert alert-success" x-show="message" x-text="message" x-cloak></div>
    <div x-show="!message">
        <div class="help-block text-danger" x-show="error('_form')" x-text="error('_form')" x-cloak></div>
        <x-form-group name="name" label="Ваше имя" for="callback-name" required>
            <input type="text" name="name" id="callback-name" value="{{ old('name') }}" class="form-control" autocomplete="name" required />
        </x-form-group>
        <x-form-group name="phone" label="Контактный номер телефона" for="callback-phone" required>
            <input type="tel" name="phone" id="callback-phone" value="{{ old('phone') }}" class="form-control" autocomplete="tel" placeholder="+7 (999) 999-99-99" required x-phone-mask />
        </x-form-group>
        <x-form-group name="comment" label="Комментарий" for="callback-comment">
            <textarea name="comment" id="callback-comment" rows="3" class="form-control">{{ old('comment') }}</textarea>
        </x-form-group>
        <button type="submit" class="btn btn-lg btn-primary" :disabled="sending">Отправить</button>
    </div>
</form>
