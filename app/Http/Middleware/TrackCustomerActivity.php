<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackCustomerActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $prefix = trim((string) config('moonshine.prefix', 'admin'), '/');

        if ($prefix !== '' && $request->is($prefix, $prefix.'/*')) {
            return $response;
        }

        $customer = auth('web')->user();

        if ($customer instanceof Customer) {
            Customer::query()->whereKey($customer->getKey())
                ->where(fn ($query) => $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<=', now()->subMinute()))
                ->toBase()->update(['last_seen_at' => now()]);
        }

        return $response;
    }
}
