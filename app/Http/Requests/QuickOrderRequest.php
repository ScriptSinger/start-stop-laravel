<?php

namespace App\Http\Requests;

use App\Rules\PhoneNumber;
use App\Services\Cart\Cart;
use Illuminate\Foundation\Http\FormRequest;

class QuickOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', new PhoneNumber],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.Cart::MAX_QUANTITY],
            'trade_in' => ['nullable', 'boolean'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите имя.',
            'name.max' => 'Имя слишком длинное.',
            'phone.required' => 'Укажите телефон — по нему мы подтвердим заказ.',
            'phone.max' => 'Проверьте номер телефона.',
            'quantity.*' => 'Количество — от 1 до '.Cart::MAX_QUANTITY.'.',
            'comment.max' => 'Комментарий слишком длинный (до 1000 символов).',
        ];
    }
}
