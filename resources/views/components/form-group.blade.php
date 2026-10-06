@props(['name', 'label', 'for', 'required' => false])
{{-- Поле формы заявки: ошибка с сервера (обычная отправка) или из ответа
     на отправку в окне (компонент ajaxForm). --}}
<div @class(['form-group', 'required' => $required, 'has-error' => $errors->has($name)]) :class="error(@js($name)) && 'has-error'">
    <label class="control-label" for="{{ $for }}">{{ $label }}</label>
    {{ $slot }}
    @error($name)<span class="help-block">{{ $message }}</span>@enderror
    <span class="help-block" x-show="error(@js($name))" x-text="error(@js($name))" x-cloak></span>
</div>
