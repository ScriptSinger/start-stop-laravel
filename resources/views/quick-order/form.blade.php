{{-- Форма «Быстрый заказ» одного товара: в окне и на странице /quick-order/{товар}. --}}
<form method="post" action="{{ route('quick-order.store', $product) }}" class="js-modal-form uni-form">
    @csrf
    <p><strong>{{ $product->name }}</strong> — {{ number_format($product->displayPrice(), 0, '', '') }}р.</p>
    @if ($product->hasTradeIn())
        <div class="checkbox">
            <label>
                <input type="checkbox" name="trade_in" value="1" @checked(old('trade_in'))>
                Сдаю старый аккумулятор: −{{ number_format((float) $product->trade_in_discount, 0, '', '') }}р. (цена {{ number_format($product->priceFor(true), 0, '', '') }}р.)
            </label>
        </div>
    @endif
    <div class="form-group required @error('name') has-error @enderror">
        <label class="control-label" for="quick-order-name">Ваше имя</label>
        <input type="text" name="name" id="quick-order-name" value="{{ old('name') }}" class="form-control" autocomplete="name" required />
        @error('name')<span class="help-block">{{ $message }}</span>@enderror
    </div>
    <div class="form-group required @error('phone') has-error @enderror">
        <label class="control-label" for="quick-order-phone">Контактный номер телефона</label>
        <input type="tel" name="phone" id="quick-order-phone" value="{{ old('phone') }}" class="form-control" autocomplete="tel" placeholder="+7 (999) 999-99-99" required />
        @error('phone')<span class="help-block">{{ $message }}</span>@enderror
    </div>
    <div class="form-group @error('quantity') has-error @enderror">
        <label class="control-label" for="quick-order-quantity">Количество</label>
        <input type="number" name="quantity" id="quick-order-quantity" value="{{ old('quantity', 1) }}" min="1" max="{{ \App\Services\Cart\Cart::MAX_QUANTITY }}" class="form-control" style="max-width: 100px;" />
        @error('quantity')<span class="help-block">{{ $message }}</span>@enderror
    </div>
    <div class="form-group @error('comment') has-error @enderror">
        <label class="control-label" for="quick-order-comment">Комментарий</label>
        <textarea name="comment" id="quick-order-comment" rows="3" class="form-control">{{ old('comment') }}</textarea>
        @error('comment')<span class="help-block">{{ $message }}</span>@enderror
    </div>
    <button type="submit" class="btn btn-lg btn-primary">Отправить заказ</button>
    <p class="help-block">Менеджер перезвонит, чтобы подтвердить заказ, способ получения и оплату.</p>
</form>
