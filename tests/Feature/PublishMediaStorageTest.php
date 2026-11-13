<?php

namespace Tests\Feature;

use Aws\Command;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class PublishMediaStorageTest extends TestCase
{
    public function test_dry_run_preserves_policy_without_writing(): void
    {
        $client = $this->client();
        $client->shouldReceive('getBucketPolicy')->once()->with(['Bucket' => 'test-media'])
            ->andReturn(new Result(['Policy' => json_encode(['Version' => '2012-10-17', 'Statement' => []])]));
        $client->shouldNotReceive('putBucketPolicy');

        $this->artisan('media:publish', ['bucket' => 'test-media'])->expectsOutputToContain('Пробный режим')->assertSuccessful();
    }

    public function test_apply_adds_only_object_read_and_preserves_existing_statement(): void
    {
        $existing = ['Sid' => 'ExistingRule', 'Effect' => 'Deny', 'Principal' => '*', 'Action' => 's3:DeleteObject', 'Resource' => 'arn:aws:s3:::test-media/*'];
        $saved = null;
        $client = $this->client();
        $client->shouldReceive('getBucketPolicy')->twice()->andReturnUsing(function () use (&$saved, $existing): Result {
            return new Result(['Policy' => $saved ?? json_encode(['Version' => '2012-10-17', 'Statement' => [$existing]])]);
        });
        $client->shouldReceive('putBucketPolicy')->once()->andReturnUsing(function (array $request) use (&$saved, $existing): Result {
            $this->assertSame('test-media', $request['Bucket']);
            $saved = $request['Policy'];
            $policy = json_decode($saved, true);
            $this->assertSame($existing, $policy['Statement'][0]);
            $this->assertSame('s3:GetObject', $policy['Statement'][1]['Action']);
            $this->assertSame(['arn:aws:s3:::test-media/media/products/*', 'arn:aws:s3:::test-media/catalog/*'], $policy['Statement'][1]['Resource']);

            return new Result;
        });

        $this->artisan('media:publish', ['bucket' => 'test-media', '--apply' => true])->assertSuccessful();
    }

    public function test_access_denied_does_not_replace_unknown_policy_or_expose_response(): void
    {
        $client = $this->client();
        $client->shouldReceive('getBucketPolicy')->once()->andThrow(new S3Exception('sensitive details', new Command('GetBucketPolicy'), ['code' => 'AccessDenied']));
        $client->shouldNotReceive('putBucketPolicy');

        $this->artisan('media:publish', ['bucket' => 'test-media', '--apply' => true])
            ->doesntExpectOutputToContain('sensitive details')->assertFailed();
    }

    private function client(): S3Client
    {
        $client = Mockery::mock(S3Client::class);
        $disk = Mockery::mock(AwsS3V3Adapter::class);
        $disk->shouldReceive('getClient')->andReturn($client);
        Storage::set('media', $disk);

        return $client;
    }
}
