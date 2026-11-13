<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CheckMediaStorageTest extends TestCase
{
    public function test_checks_real_bytes_through_unsigned_url_and_removes_probe(): void
    {
        $disk = Storage::fake('media', ['url' => 'https://media.example']);
        Http::preventStrayRequests();
        Http::fake(['https://media.example/media/products/*' => function (Request $request) use ($disk) {
            $this->assertFalse($request->hasHeader('Authorization'));

            return Http::response($disk->get(ltrim(parse_url($request->url(), PHP_URL_PATH), '/')), 200);
        }]);

        $this->artisan('media:check', ['--write' => true])
            ->expectsOutputToContain('Публичное чтение и целостность: OK')->assertSuccessful();

        $disk->assertDirectoryEmpty('/');
        Http::assertSentCount(1);
    }

    public function test_private_bucket_fails_public_check_and_probe_is_removed(): void
    {
        $disk = Storage::fake('media', ['url' => 'https://media.example']);
        Http::preventStrayRequests();
        Http::fake(['https://media.example/media/products/*' => Http::response('Forbidden', 403)]);

        $this->artisan('media:check', ['--write' => true])
            ->expectsOutputToContain('Анонимный GET: HTTP 403')->assertFailed();

        $disk->assertDirectoryEmpty('/');
    }

    public function test_does_not_write_without_explicit_option(): void
    {
        $disk = Storage::fake('media');

        $this->artisan('media:check')->assertFailed();

        $disk->assertDirectoryEmpty('/');
    }
}
