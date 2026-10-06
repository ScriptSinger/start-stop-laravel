<?php

namespace App\Actions\Fortify;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * @return array<int, Rule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', Password::min(8), 'confirmed'];
    }

    /**
     * Сообщения по-русски прямо здесь, как в формах заказа: форма должна быть
     * русской независимо от языка приложения.
     *
     * @return array<string, string>
     */
    protected function passwordMessages(): array
    {
        return [
            'password.required' => 'Придумайте пароль.',
            'password.min' => 'Пароль — не короче 8 символов.',
            'password.confirmed' => 'Пароли не совпадают.',
        ];
    }
}
