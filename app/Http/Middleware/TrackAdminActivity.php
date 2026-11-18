<?php

namespace App\Http\Middleware;

use App\Models\MoonshineUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Отмечает присутствие администратора в last_seen_at (не чаще раза в минуту).
 */
class TrackAdminActivity
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $admin = auth('moonshine')->user();

        if ($admin !== null) {
            MoonshineUser::query()->whereKey($admin->getAuthIdentifier())
                ->where(fn ($query) => $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<=', now()->subMinute()))
                ->toBase()->update(['last_seen_at' => now()]);
        }

        return $response;
    }
}
