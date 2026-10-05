{{-- Форма «Задать вопрос» о товаре: в окне и на странице /product-question/{товар}. --}}
<form method="post" action="{{ route('product-question.store', $product) }}" class="js-modal-form uni-form">
    @csrf
    <p><strong>{{ $product->name }}</strong></p>
    <div class="form-group required @error('name') has-error @enderror">
        <label class="control-label" for="question-name">Ваше имя</label>
        <input type="text" name="name" id="question-name" value="{{ old('name') }}" class="form-control" autocomplete="name" required />
        @error('name')<span class="help-block">{{ $message }}</span>@enderror
    </div>
    <div class="form-group required @error('phone') has-error @enderror">
        <label class="control-label" for="question-phone">Контактный номер телефона</label>
        <input type="tel" name="phone" id="question-phone" value="{{ old('phone') }}" class="form-control" autocomplete="tel" placeholder="+7 (999) 999-99-99" required />
        @error('phone')<span class="help-block">{{ $message }}</span>@enderror
    </div>
    <div class="form-group required @error('comment') has-error @enderror">
        <label class="control-label" for="question-comment">Ваш вопрос</label>
        <textarea name="comment" id="question-comment" rows="4" class="form-control" required>{{ old('comment') }}</textarea>
        @error('comment')<span class="help-block">{{ $message }}</span>@enderror
    </div>
    <button type="submit" class="btn btn-lg btn-primary">Отправить</button>
</form>
