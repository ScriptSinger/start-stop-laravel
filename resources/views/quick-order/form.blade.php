{{-- Форма «Быстрый заказ» одного товара: в окне ($store.modal) и на странице /quick-order/{товар}. --}}
<form method="post" action="{{ route('quick-order.store', $product) }}" class="uni-form" x-data="ajaxForm" @submit="submit">
    @csrf
    <div class="alert alert-success" x-show="message" x-text="message" x-cloak></div>
    <div x-show="!message">
        <div class="help-block text-danger" x-show="error('_form')" x-text="error('_form')" x-cloak></div>
        <p><strong>{{ $product->name }}</strong> — {{ number_format($product->displayPrice(), 0, '', '') }}р.</p>
        @if ($product->hasTradeIn())
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="trade_in" value="1" @checked(old('trade_in'))>
                    Сдаю старый аккумулятор: −{{ number_format((float) $product->trade_in_discount, 0, '', '') }}р. (цена {{ number_format($product->priceFor(true), 0, '', '') }}р.)
                </label>
            </div>
        @endif
        <x-form-group name="name" label="Ваше имя" for="quick-order-name" required>
            <input type="text" name="name" id="quick-order-name" value="{{ old('name') }}" class="form-control" autocomplete="name" required />
        </x-form-group>
        <x-form-group name="phone" label="Контактный номер телефона" for="quick-order-phone" required>
            <input type="tel" name="phone" id="quick-order-phone" value="{{ old('phone') }}" class="form-control" autocomplete="tel" placeholder="+7 (999) 999-99-99" required x-phone-mask />
        </x-form-group>
        <x-form-group name="quantity" label="Количество" for="quick-order-quantity">
            <input type="number" name="quantity" id="quick-order-quantity" value="{{ old('quantity', 1) }}" min="1" max="{{ \App\Services\Cart\Cart::MAX_QUANTITY }}" class="form-control quick-order__quantity" />
        </x-form-group>
        <x-form-group name="comment" label="Комментарий" for="quick-order-comment">
            <textarea name="comment" id="quick-order-comment" rows="3" class="form-control">{{ old('comment') }}</textarea>
        </x-form-group>
        <button type="submit" class="btn btn-lg btn-primary" :disabled="sending">Отправить заказ</button>
        <p class="help-block">Менеджер перезвонит, чтобы подтвердить заказ, способ получения и оплату.</p>
    </div>
</form>
