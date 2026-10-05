{{-- Форма «Заказать звонок»: в окне (data-modal-url) и на странице /callback. --}}
<form method="post" action="{{ route('callback.store') }}" class="js-modal-form uni-form">
    @csrf
    <div class="form-group required @error('name') has-error @enderror">
        <label class="control-label" for="callback-name">Ваше имя</label>
        <input type="text" name="name" id="callback-name" value="{{ old('name') }}" class="form-control" autocomplete="name" required />
        @error('name')<span class="help-block">{{ $message }}</span>@enderror
    </div>
    <div class="form-group required @error('phone') has-error @enderror">
        <label class="control-label" for="callback-phone">Контактный номер телефона</label>
        <input type="tel" name="phone" id="callback-phone" value="{{ old('phone') }}" class="form-control" autocomplete="tel" placeholder="+7 (999) 999-99-99" required />
        @error('phone')<span class="help-block">{{ $message }}</span>@enderror
    </div>
    <div class="form-group @error('comment') has-error @enderror">
        <label class="control-label" for="callback-comment">Комментарий</label>
        <textarea name="comment" id="callback-comment" rows="3" class="form-control">{{ old('comment') }}</textarea>
        @error('comment')<span class="help-block">{{ $message }}</span>@enderror
    </div>
    <button type="submit" class="btn btn-lg btn-primary">Отправить</button>
</form>
