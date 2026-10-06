{{-- Форма «Задать вопрос» о товаре: в окне ($store.modal) и на странице /product-question/{товар}. --}}
<form method="post" action="{{ route('product-question.store', $product) }}" class="uni-form" x-data="ajaxForm" @submit="submit">
    @csrf
    <div class="alert alert-success" x-show="message" x-text="message" x-cloak></div>
    <div x-show="!message">
        <div class="help-block text-danger" x-show="error('_form')" x-text="error('_form')" x-cloak></div>
        <p><strong>{{ $product->name }}</strong></p>
        <x-form-group name="name" label="Ваше имя" for="question-name" required>
            <input type="text" name="name" id="question-name" value="{{ old('name') }}" class="form-control" autocomplete="name" required />
        </x-form-group>
        <x-form-group name="phone" label="Контактный номер телефона" for="question-phone" required>
            <input type="tel" name="phone" id="question-phone" value="{{ old('phone') }}" class="form-control" autocomplete="tel" placeholder="+7 (999) 999-99-99" required x-phone-mask />
        </x-form-group>
        <x-form-group name="comment" label="Ваш вопрос" for="question-comment" required>
            <textarea name="comment" id="question-comment" rows="4" class="form-control" required>{{ old('comment') }}</textarea>
        </x-form-group>
        <button type="submit" class="btn btn-lg btn-primary" :disabled="sending">Отправить</button>
    </div>
</form>
