<?php

namespace App\Console\Commands;

use Aws\Exception\AwsException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PublishMediaStorage extends Command
{
    protected $signature = 'media:publish {bucket : Имя отдельного бакета изображений} {--apply : Добавить правило публичного чтения media/products/* и catalog/*}';

    protected $description = 'Добавить публичное чтение фотографий, сохранив существующую политику бакета';

    public function handle(): int
    {
        $bucket = (string) $this->argument('bucket');

        if (! preg_match('/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/', $bucket)) {
            $this->error('Недопустимое имя бакета.');

            return self::FAILURE;
        }

        $statement = [
            'Sid' => 'StorefrontProductImagesRead',
            'Effect' => 'Allow',
            'Principal' => '*',
            'Action' => 's3:GetObject',
            'Resource' => ["arn:aws:s3:::{$bucket}/media/products/*", "arn:aws:s3:::{$bucket}/catalog/*"],
        ];

        try {
            $client = Storage::disk('media')->getClient();

            try {
                $response = $client->getBucketPolicy(['Bucket' => $bucket]);
                $policy = json_decode((string) $response['Policy'], true, flags: JSON_THROW_ON_ERROR);
            } catch (AwsException $exception) {
                if ($exception->getAwsErrorCode() !== 'NoSuchBucketPolicy') {
                    throw $exception;
                }

                $policy = ['Version' => '2012-10-17', 'Statement' => []];
            }

            if (! is_array($policy) || ! isset($policy['Statement']) || ! is_array($policy['Statement']) || ! array_is_list($policy['Statement'])) {
                $this->error('Неожиданный формат политики; изменений нет.');

                return self::FAILURE;
            }

            foreach ($policy['Statement'] as $existing) {
                if (($existing['Sid'] ?? null) === $statement['Sid']) {
                    if ($existing == $statement) {
                        $this->info('Правило уже установлено.');

                        return self::SUCCESS;
                    }

                    $this->error('Правило с этим Sid уже существует и отличается; изменений нет.');

                    return self::FAILURE;
                }
            }

            $this->line(json_encode($statement, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info('Существующих правил: '.count($policy['Statement']).'. Они будут сохранены.');

            if (! $this->option('apply')) {
                $this->warn('Пробный режим: политика не изменена.');

                return self::SUCCESS;
            }

            $policy['Statement'][] = $statement;
            $client->putBucketPolicy(['Bucket' => $bucket, 'Policy' => json_encode($policy, JSON_THROW_ON_ERROR)]);
            $verified = $client->getBucketPolicy(['Bucket' => $bucket]);
            $saved = json_decode((string) $verified['Policy'], true, flags: JSON_THROW_ON_ERROR);

            if ($saved != $policy) {
                $this->error('Политика отправлена, но результат требует проверки.');

                return self::FAILURE;
            }

            $this->info('Правило публичного чтения добавлено и проверено.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $status = $exception instanceof AwsException ? (string) $exception->getStatusCode() : 'n/a';
            $this->error('Операция с политикой не выполнена. HTTP '.$status.'; тип: '.class_basename($exception));

            return self::FAILURE;
        }
    }
}
