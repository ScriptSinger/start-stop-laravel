<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Начало адреса доставки для подсказок: /checkout/address-suggestions?query=…
 */
class AddressSuggestionRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:3', 'max:150'],
        ];
    }
}
