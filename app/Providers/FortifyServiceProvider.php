<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\Customer;
use Artesaos\SEOTools\Facades\SEOTools;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\View\View;
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
        Fortify::loginView(fn (Request $request) => $request->ajax()
            ? view('auth.partials.login-form', ['formId' => 'modal'])
            : $this->authView('Авторизация', 'auth.login'));
        Fortify::registerView(fn () => $this->authView('Регистрация', 'auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => $this->authView('Забыли пароль?', 'auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => $this->authView('Новый пароль', 'auth.reset-password', ['request' => $request]));

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

    /**
     * @param  array<string, mixed>  $data
     */
    private function authView(string $title, string $view, array $data = []): View
    {
        SEOTools::setTitle($title);

        return view($view, $data);
    }
}
