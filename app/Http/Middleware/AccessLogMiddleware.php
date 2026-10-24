<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AccessLogMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningInConsole()) {
            return $next($request);
        }

        $path = $request->getPathInfo();
        if ($this->shouldSkip($path)) {
            return $next($request);
        }

        $start = microtime(true);
        $response = null;

        try {
            $response = $next($request);

            return $response;
        } finally {
            $status = $response?->getStatusCode() ?? 500;
            $durationMs = (int) round((microtime(true) - $start) * 1000);
            $requestId = (string) $request->attributes->get('request_id', '');
            $responseSize = $this->getResponseSize($response);

            $payload = [
                'request_id' => $requestId !== '' ? $requestId : null,
                'method' => $request->getMethod(),
                'path' => $request->getPathInfo(),
                'query' => $this->query($request),
                'status' => $status,
                'duration_ms' => $durationMs,
                'slow_request' => $this->isSlow($durationMs),
                'response_size' => $responseSize,
                'is_suspicious' => $this->isSuspiciousPath($request->getPathInfo()),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->headers->get('referer'),
                'is_bot' => $this->isBot($request->userAgent()),
                'admin_user_id' => $this->adminUserId($request),
            ];

            Log::channel('access')->info('access', $payload);
        }
    }

    /**
     * Only asked on admin URLs: the `api` group has no session, and the
     * session guard throws when it is touched without one.
     */
    private function adminUserId(Request $request): int|string|null
    {
        $prefix = trim((string) config('moonshine.prefix', 'admin'), '/');

        if ($prefix === '' || ! $request->is($prefix, $prefix.'/*')) {
            return null;
        }

        try {
            return auth(config('moonshine.auth.guard', 'moonshine'))->id();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * UTM tags and search terms live here; capped so a hostile client cannot
     * turn the log into a dumping ground.
     */
    private function query(Request $request): ?string
    {
        $query = $request->getQueryString();

        return $query === null || $query === '' ? null : Str::limit($query, 500, '…');
    }

    private function shouldSkip(string $path): bool
    {
        $lower = strtolower($path);

        if ($lower === '/favicon.ico') {
            return true;
        }

        // Whole path segments only: a bare "/build" prefix would also swallow
        // real pages such as /builder.
        foreach (['/build', '/storage'] as $dir) {
            if ($lower === $dir || str_starts_with($lower, $dir.'/')) {
                return true;
            }
        }

        return false;
    }

    private function isBot(?string $userAgent): bool
    {
        if (! $userAgent) {
            return false;
        }

        $ua = strtolower($userAgent);

        $patterns = (array) config('access_log.bot_patterns', []);

        foreach ($patterns as $pattern) {
            if (str_contains($ua, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function isSlow(int $durationMs): bool
    {
        $threshold = (int) config('access_log.slow_threshold_ms', 1000);

        return $durationMs >= $threshold;
    }

    private function isSuspiciousPath(string $path): bool
    {
        $normalized = '/'.ltrim(strtolower($path), '/');
        $paths = (array) config('access_log.suspicious_paths', []);
        $allowlist = (array) config('access_log.well_known_allowlist', []);

        if (str_starts_with($normalized, '/.well-known')) {
            foreach ($allowlist as $allowed) {
                $allowedPath = '/'.ltrim(strtolower((string) $allowed), '/');
                if ($allowedPath !== '/' && str_starts_with($normalized, $allowedPath)) {
                    return false;
                }
            }
        }

        foreach ($paths as $group) {
            foreach ((array) $group as $susPath) {
                $needle = '/'.ltrim(strtolower((string) $susPath), '/');
                if ($needle !== '/' && str_starts_with($normalized, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getResponseSize(?Response $response): ?int
    {
        if (! $response) {
            return null;
        }

        $headerSize = $response->headers->get('Content-Length');
        if ($headerSize !== null && $headerSize !== '') {
            $size = (int) $headerSize;

            return $size >= 0 ? $size : null;
        }

        if (method_exists($response, 'getContent')) {
            $content = $response->getContent();
            if (is_string($content)) {
                return strlen($content);
            }
        }

        return null;
    }
}
