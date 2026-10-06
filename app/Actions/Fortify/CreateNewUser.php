<?php

namespace App\Actions\Fortify;

use App\Models\Customer;
use App\Rules\PhoneNumber;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Регистрация покупателя (/create-account) — поля как на старом сайте.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): Customer
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(Customer::class)],
            'phone' => ['required', 'string', 'max:30', new PhoneNumber],
            'password' => $this->passwordRules(),
            'agree' => ['accepted'],
        ], [
            'name.required' => 'Укажите имя.',
            'email.required' => 'Укажите e-mail — по нему вы будете входить.',
            'email.email' => 'Проверьте e-mail.',
            // Покупатели старого сайта уже есть в базе — им нужен не новый аккаунт, а пароль.
            'email.unique' => 'Этот e-mail уже зарегистрирован. Войдите или восстановите пароль.',
            'phone.required' => 'Укажите телефон.',
            'agree.accepted' => 'Подтвердите согласие с политикой безопасности.',
            ...$this->passwordMessages(),
        ])->validate();

        return Customer::create([
            'name' => $input['name'],
            'email' => mb_strtolower($input['email']),
            'phone' => $input['phone'],
            'password' => $input['password'],
        ]);
    }
}
