<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Покупатель: входит на сайт по e-mail и паролю (Fortify), видит свои заказы.
 * Администраторы — отдельно, в MoonShine (moonshine_users).
 */
class Customer extends Authenticatable
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'legacy_password_hash',
        'legacy_password_salt',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Пароль со старого сайта: OpenCart 3 хранил sha1(соль . sha1(соль . sha1(пароль))).
     * Подошёл — пароль пересохраняется обычным хешем, старый стирается.
     */
    public function upgradeLegacyPassword(string $password): bool
    {
        if (! $this->legacy_password_hash || ! $this->legacy_password_salt) {
            return false;
        }

        $salt = $this->legacy_password_salt;
        $expected = sha1($salt.sha1($salt.sha1($password)));

        if (! hash_equals($this->legacy_password_hash, $expected)) {
            return false;
        }

        $this->forceFill([
            'password' => $password,
            'legacy_password_hash' => null,
            'legacy_password_salt' => null,
        ])->save();

        return true;
    }
}
