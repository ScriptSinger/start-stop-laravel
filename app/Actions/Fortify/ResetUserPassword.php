<?php

namespace App\Actions\Fortify;

use App\Models\Customer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Новый пароль по ссылке из письма. Пароль со старого сайта больше не нужен.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(Customer $user, array $input): void
    {
        Validator::make($input, ['password' => $this->passwordRules()], $this->passwordMessages())->validate();

        $user->forceFill([
            'password' => $input['password'],
            'legacy_password_hash' => null,
            'legacy_password_salt' => null,
        ])->save();
    }
}
