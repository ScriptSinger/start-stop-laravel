<?php

namespace Tests\Feature;

use App\Http\Middleware\AccessLogMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery;
use Psr\Log\LoggerInterface;
use ReflectionProperty;
use Tests\TestCase;

class AccessLogPrivacyTest extends TestCase
{
    public function test_reset_link_secrets_are_absent_from_access_log(): void
    {
        $payload = $this->loggedPayload('/reset-password/private-token?email=private%40example.com',
            'https://user:password@shop.test/reset-password/private-token?email=private@example.com#secret');

        $this->assertSame('/reset-password/[REDACTED]', $payload['path']);
        $this->assertNull($payload['query']);
        $this->assertSame('https://shop.test', $payload['referer']);
        $this->assertStringNotContainsString('private', json_encode($payload));
    }

    public function test_sensitive_query_values_are_redacted_but_search_is_preserved(): void
    {
        $payload = $this->loggedPayload('/search?q=battery&EMAIL=private&access_token=secret&nested[password]=hidden');

        $this->assertStringContainsString('q=battery', $payload['query']);
        foreach (['private', 'secret', 'hidden'] as $value) {
            $this->assertStringNotContainsString($value, $payload['query']);
        }
    }

    public function test_oauth_query_and_referrer_credentials_are_not_logged(): void
    {
        $payload = $this->loggedPayload('/auth/yandex/callback?code=private&state=secret', 'not a URL');

        $this->assertNull($payload['query']);
        $this->assertNull($payload['referer']);
    }

    /** @return array<string, mixed> */
    private function loggedPayload(string $url, ?string $referer = null): array
    {
        $payload = [];
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')->once()->with('access', Mockery::on(function (array $context) use (&$payload): bool {
            $payload = $context;

            return true;
        }));
        Log::shouldReceive('channel')->with('access')->once()->andReturn($logger);
        $request = Request::create($url);
        if ($referer !== null) {
            $request->headers->set('referer', $referer);
        }
        $property = new ReflectionProperty($this->app, 'isRunningInConsole');
        $previous = $property->getValue($this->app);
        $property->setValue($this->app, false);
        try {
            (new AccessLogMiddleware)->handle($request, fn () => response('ok'));
        } finally {
            $property->setValue($this->app, $previous);
        }

        return $payload;
    }
}
