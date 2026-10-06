<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+7 (987) '.fake()->numerify('###-##-##'),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Клиент со старого сайта: пароля ещё нет, есть хеш OpenCart.
     */
    public function legacyPassword(string $password, string $salt = 'abcdefghi'): static
    {
        return $this->state(fn (): array => [
            'password' => null,
            'legacy_password_hash' => sha1($salt.sha1($salt.sha1($password))),
            'legacy_password_salt' => $salt,
        ]);
    }
}
