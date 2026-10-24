<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);

        // Context lands in `extra` of every log record written during this
        // request (laravel.log, auth.log, ...) and is carried into jobs
        // dispatched from it, so an error can be traced back to its access
        // log line and the lead notification jobs it queued.
        Context::add('request_id', $requestId);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
