<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class YandexAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        return $this->start($request, null);
    }

    public function link(Request $request): RedirectResponse
    {
        return $this->start($request, $request->user()->getKey());
    }

    private function start(Request $request, ?int $customerId): RedirectResponse
    {
        abort_unless(filled(config('services.yandex.client_id')) && filled(config('services.yandex.client_secret')) && filled(config('services.yandex.redirect')), 404);

        $state = Str::random(64);
        $verifier = Str::random(64);
        $request->session()->put('yandex_oauth', [
            'state' => $state,
            'verifier' => $verifier,
            'customer_id' => $customerId,
            'created_at' => now()->timestamp,
        ]);

        return redirect()->away('https://oauth.yandex.ru/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.yandex.client_id'),
            'redirect_uri' => config('services.yandex.redirect'),
            'scope' => 'login:info login:email',
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986));
    }

    public function callback(Request $request): RedirectResponse
    {
        $flow = $request->session()->pull('yandex_oauth');
        $state = $request->query('state');
        $customerId = is_array($flow) ? ($flow['customer_id'] ?? null) : null;
        $destination = $customerId !== null ? 'account' : 'login';

        if (! is_array($flow) || ! is_string($flow['state'] ?? null) || ! is_string($state)
            || ! hash_equals($flow['state'], $state) || ! is_string($flow['verifier'] ?? null)
            || ! is_int($flow['created_at'] ?? null) || now()->timestamp - $flow['created_at'] > 600
            || $request->user()?->getKey() !== $customerId) {
            return redirect()->route($destination)->withErrors(['yandex' => 'Не удалось подтвердить вход через Яндекс. Попробуйте ещё раз.']);
        }

        if (! is_string($request->query('code')) || $request->query('code') === '') {
            return redirect()->route($destination)->withErrors(['yandex' => 'Вход через Яндекс отменён или не завершён.']);
        }

        try {
            $token = Http::asForm()->acceptJson()->timeout(10)->post('https://oauth.yandex.ru/token', [
                'grant_type' => 'authorization_code',
                'code' => $request->query('code'),
                'client_id' => config('services.yandex.client_id'),
                'client_secret' => config('services.yandex.client_secret'),
                'code_verifier' => $flow['verifier'],
            ])->throw()->json('access_token');

            if (! is_string($token) || $token === '') {
                return redirect()->route($destination)->withErrors(['yandex' => 'Не удалось получить данные Яндекс ID.']);
            }

            $profile = Http::acceptJson()->timeout(10)->withToken($token, 'OAuth')
                ->get('https://login.yandex.ru/info', ['format' => 'json'])->throw()->json();
        } catch (ConnectionException|RequestException $exception) {
            return redirect()->route($destination)->withErrors(['yandex' => 'Яндекс ID временно недоступен. Попробуйте позже.']);
        }

        $yandexId = $profile['id'] ?? null;
        if (! is_string($yandexId) || $yandexId === '' || mb_strlen($yandexId) > 255
            || ($profile['client_id'] ?? null) !== config('services.yandex.client_id')) {
            return redirect()->route($destination)->withErrors(['yandex' => 'Не удалось подтвердить данные Яндекс ID.']);
        }

        $linkedCustomer = Customer::query()->where('yandex_id', $yandexId)->first();

        if ($customerId !== null) {
            if ($linkedCustomer && $linkedCustomer->getKey() !== $customerId) {
                return redirect()->route('account')->withErrors(['yandex' => 'Этот Яндекс ID уже связан с другим аккаунтом.']);
            }

            try {
                Customer::query()->whereKey($customerId)->whereNull('yandex_id')->update(['yandex_id' => $yandexId]);
            } catch (UniqueConstraintViolationException $exception) {
                return redirect()->route('account')->withErrors(['yandex' => 'Этот Яндекс ID уже связан с другим аккаунтом.']);
            }

            if (! Customer::query()->whereKey($customerId)->where('yandex_id', $yandexId)->exists()) {
                return redirect()->route('account')->withErrors(['yandex' => 'К аккаунту уже привязан другой Яндекс ID.']);
            }

            return redirect()->route('account')->with('status', 'Яндекс ID привязан к вашему аккаунту.');
        }

        if (! $linkedCustomer) {
            $email = $profile['default_email'] ?? null;
            if (! is_string($email) || mb_strlen($email) > 255 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return redirect()->route('login')->withErrors(['yandex' => 'Разрешите Яндексу передать адрес электронной почты.']);
            }

            $email = mb_strtolower($email);
            if (Customer::query()->where('email', $email)->exists()) {
                return redirect()->route('login')->withErrors(['yandex' => 'Этот e-mail уже зарегистрирован. Войдите с паролем и привяжите Яндекс ID в личном кабинете.']);
            }

            $name = $profile['real_name'] ?? $profile['display_name'] ?? $profile['login'] ?? '';
            $name = is_string($name) && trim($name) !== '' ? Str::limit(trim($name), 255, '') : 'Покупатель';

            try {
                $linkedCustomer = new Customer(['name' => $name, 'email' => $email]);
                $linkedCustomer->yandex_id = $yandexId;
                $linkedCustomer->save();
            } catch (UniqueConstraintViolationException $exception) {
                return redirect()->route('login')->withErrors(['yandex' => 'Не удалось создать аккаунт. Войдите с паролем или попробуйте ещё раз.']);
            }
        }

        Auth::guard('web')->login($linkedCustomer);
        $request->session()->regenerate();

        return redirect()->intended(route('account'));
    }
}
