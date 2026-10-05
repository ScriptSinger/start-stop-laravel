<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Российский номер телефона в свободной записи: «+7 (987) 250-00-26»,
 * «89872500026». Допустимы цифры, пробелы, скобки, «+» и «-»; цифр 10–11
 * (до 15 — на случай иностранного номера).
 */
class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^[\d\s()+\-]+$/', $value) !== 1) {
            $fail('В телефоне могут быть только цифры, пробелы, скобки, «+» и «-».');

            return;
        }

        $digits = strlen((string) preg_replace('/\D/', '', $value));

        if ($digits < 10 || $digits > 15) {
            $fail('Проверьте номер телефона: нужно 10–11 цифр.');
        }
    }
}
