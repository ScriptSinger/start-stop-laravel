<?php

namespace App\Http\Requests;

use App\Rules\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class CallbackRequestRequest extends FormRequest
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
            'phone.required' => 'Укажите телефон — мы перезвоним на него.',
            'phone.max' => 'Проверьте номер телефона.',
            'comment.max' => 'Комментарий слишком длинный (до 1000 символов).',
        ];
    }
}
