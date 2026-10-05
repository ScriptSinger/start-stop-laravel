<?php

namespace App\Http\Requests;

use App\Rules\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class ProductQuestionRequest extends FormRequest
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
            'comment' => ['required', 'string', 'max:1000'],
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
            'phone.required' => 'Укажите телефон — по нему мы ответим.',
            'phone.max' => 'Проверьте номер телефона.',
            'comment.required' => 'Напишите вопрос.',
            'comment.max' => 'Вопрос слишком длинный (до 1000 символов).',
        ];
    }
}
