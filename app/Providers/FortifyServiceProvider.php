<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\Customer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

/**
 * Вход покупателей на витрину: e-mail и пароль, регистрация, восстановление
 * пароля письмом. Страницы — в теме сайта (resources/views/auth).
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // В окне «Авторизация» (шапка, оформление заказа) — только форма.
        Fortify::loginView(fn (Request $request) => $request->ajax() ? view('auth.partials.login-form', ['formId' => 'modal']) : view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));

        // Покупатели старого сайта входят своим прежним паролем (хеш OpenCart),
        // он сразу пересохраняется обычным хешем.
        Fortify::authenticateUsing(function (Request $request): ?Customer {
            $customer = Customer::query()->where('email', mb_strtolower((string) $request->input('email')))->first();
            $password = (string) $request->input('password');

            if (! $customer) {
                return null;
            }

            if ($customer->password && Hash::check($password, $customer->password)) {
                return $customer;
            }

            return $customer->upgradeLegacyPassword($password) ? $customer : null;
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
