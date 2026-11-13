<?php

namespace App\Http\Controllers;

use App\Actions\MergeSocialCustomer;
use App\Models\Customer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class VkAuthController extends Controller
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
        abort_unless(filled(config('services.vkid.client_id')) && filled(config('services.vkid.redirect')), 404);

        $state = Str::random(64);
        $verifier = Str::random(64);
        $request->session()->put('vkid_oauth', [
            'state' => $state,
            'verifier' => $verifier,
            'customer_id' => $customerId,
            'created_at' => now()->timestamp,
        ]);

        return redirect()->away('https://id.vk.ru/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.vkid.client_id'),
            'redirect_uri' => config('services.vkid.redirect'),
            'scope' => 'email',
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 's256',
        ], '', '&', PHP_QUERY_RFC3986));
    }

    public function callback(Request $request): RedirectResponse
    {
        $flow = $request->session()->pull('vkid_oauth');
        $state = $request->query('state');
        $customerId = is_array($flow) ? ($flow['customer_id'] ?? null) : null;
        $destination = $customerId !== null ? 'account' : 'login';

        if (! is_array($flow) || ! is_string($flow['state'] ?? null) || ! is_string($state)
            || ! hash_equals($flow['state'], $state) || ! is_string($flow['verifier'] ?? null)
            || ! is_int($flow['created_at'] ?? null) || now()->timestamp - $flow['created_at'] > 600
            || $request->user()?->getKey() !== $customerId) {
            return redirect()->route($destination)->withErrors(['vkid' => 'Не удалось подтвердить вход через VK ID. Попробуйте ещё раз.']);
        }

        $code = $request->query('code');
        $deviceId = $request->query('device_id');

        if (! is_string($code) || $code === '' || ! is_string($deviceId) || $deviceId === '' || mb_strlen($deviceId) > 255) {
            return redirect()->route($destination)->withErrors(['vkid' => 'Вход через VK ID отменён или не завершён.']);
        }

        try {
            $tokenResponse = Http::asForm()->acceptJson()->timeout(10)
                ->post('https://id.vk.ru/oauth2/auth?'.http_build_query([
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => config('services.vkid.redirect'),
                    'client_id' => config('services.vkid.client_id'),
                    'code_verifier' => $flow['verifier'],
                    'state' => $state,
                    'device_id' => $deviceId,
                ], '', '&', PHP_QUERY_RFC3986), ['code' => $code])->throw()->json();

            if (! is_array($tokenResponse) || ! is_string($tokenResponse['access_token'] ?? null)
                || $tokenResponse['access_token'] === '' || ($tokenResponse['state'] ?? null) !== $state) {
                return redirect()->route($destination)->withErrors(['vkid' => 'Не удалось подтвердить ответ VK ID.']);
            }

            $profileResponse = Http::asForm()->acceptJson()->timeout(10)
                ->post('https://id.vk.ru/oauth2/user_info?'.http_build_query([
                    'client_id' => config('services.vkid.client_id'),
                ], '', '&', PHP_QUERY_RFC3986), [
                    'access_token' => $tokenResponse['access_token'],
                ])->throw()->json();
        } catch (ConnectionException|RequestException $exception) {
            return redirect()->route($destination)->withErrors(['vkid' => 'VK ID временно недоступен. Попробуйте позже.']);
        }

        $profile = is_array($profileResponse) ? ($profileResponse['user'] ?? null) : null;
        $profileId = is_array($profile) ? ($profile['user_id'] ?? null) : null;
        $tokenId = $tokenResponse['user_id'] ?? null;

        if ((! is_string($profileId) && ! is_int($profileId)) || (! is_string($tokenId) && ! is_int($tokenId))
            || ! ctype_digit((string) $profileId) || (string) $profileId !== (string) $tokenId
            || mb_strlen((string) $profileId) > 255) {
            return redirect()->route($destination)->withErrors(['vkid' => 'Не удалось подтвердить данные VK ID.']);
        }

        $vkId = (string) $profileId;
        $linkedCustomer = Customer::query()->where('vk_id', $vkId)->first();

        if ($customerId !== null) {
            if ($linkedCustomer && $linkedCustomer->getKey() !== $customerId) {
                return redirect()->route('account')->withErrors(['vkid' => 'Этот VK ID уже связан с другим аккаунтом.']);
            }

            try {
                Customer::query()->whereKey($customerId)->whereNull('vk_id')->update(['vk_id' => $vkId]);
            } catch (UniqueConstraintViolationException $exception) {
                return redirect()->route('account')->withErrors(['vkid' => 'Этот VK ID уже связан с другим аккаунтом.']);
            }

            if (! Customer::query()->whereKey($customerId)->where('vk_id', $vkId)->exists()) {
                return redirect()->route('account')->withErrors(['vkid' => 'К аккаунту уже привязан другой VK ID.']);
            }

            return redirect()->route('account')->with('status', 'VK ID привязан к вашему аккаунту.');
        }

        if (! $linkedCustomer) {
            $email = $profile['email'] ?? null;
            $email = is_string($email) && mb_strlen($email) <= 255 && filter_var($email, FILTER_VALIDATE_EMAIL)
                ? mb_strtolower($email)
                : null;
            $emailIsTaken = $email !== null && Customer::query()->where('email', $email)->exists();
            $firstName = is_string($profile['first_name'] ?? null) ? $profile['first_name'] : '';
            $lastName = is_string($profile['last_name'] ?? null) ? $profile['last_name'] : '';
            $name = trim($firstName.' '.$lastName);
            $name = $name !== '' ? Str::limit($name, 255, '') : 'Покупатель';

            try {
                $linkedCustomer = new Customer(['name' => $name, 'email' => $emailIsTaken ? null : $email]);
                $linkedCustomer->vk_id = $vkId;
                $linkedCustomer->vk_email = $email;
                $linkedCustomer->save();
            } catch (UniqueConstraintViolationException $exception) {
                return redirect()->route('login')->withErrors(['vkid' => 'Не удалось создать аккаунт. Попробуйте ещё раз.']);
            }
        }

        Auth::guard('web')->login($linkedCustomer);
        $request->session()->regenerate();

        return redirect()->intended(route('account'));
    }

    public function merge(Request $request, MergeSocialCustomer $mergeCustomer): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        $customer = $request->user();

        if ($customer->vk_id === null || $customer->email !== null || $customer->vk_email === null) {
            return redirect()->route('account')->withErrors(['vkid' => 'Объединение аккаунтов недоступно.']);
        }

        $mergedCustomer = $mergeCustomer->vk($customer, (string) $request->input('password'));

        if (! $mergedCustomer) {
            return redirect()->route('account')->withErrors(['password' => 'Не удалось подтвердить прежний аккаунт. Проверьте пароль.']);
        }

        Auth::guard('web')->login($mergedCustomer);
        $request->session()->regenerate();

        return redirect()->route('account')->with('status', 'VK ID привязан. История заказов доступна в личном кабинете.');
    }
}
