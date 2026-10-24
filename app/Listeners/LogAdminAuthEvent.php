<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;

/**
 * Writes MoonShine admin sign-ins, failed attempts and sign-outs to the
 * `auth` log channel. Credentials are never logged beyond the login name —
 * a mistyped password is often the real one for another account.
 */
class LogAdminAuthEvent
{
    public function handle(Login|Failed|Logout $event): void
    {
        if ($event->guard !== config('moonshine.auth.guard', 'moonshine')) {
            return;
        }

        $request = request();

        $context = [
            'request_id' => $request->attributes->get('request_id'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        if ($event instanceof Failed) {
            $username = config('moonshine.user_fields.username', 'email');

            Log::channel('auth')->warning('login_failed', $context + [
                'login' => $event->credentials[$username] ?? null,
                'user_exists' => $event->user !== null,
            ]);

            return;
        }

        Log::channel('auth')->info($event instanceof Login ? 'login' : 'logout', $context + [
            'user_id' => $event->user?->getAuthIdentifier(),
        ]);
    }
}
