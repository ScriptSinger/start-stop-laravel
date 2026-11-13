<?php

namespace App\Console\Commands;

use App\Services\Catalog\ProductMedia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CheckMediaStorage extends Command
{
    protected $signature = 'media:check {--write : Создать и удалить уникальное тестовое PNG для проверки записи и публичной выдачи}';

    protected $description = 'Проверить запись, чтение и публичную выдачу media без вывода настроек и секретов';

    public function handle(): int
    {
        if (! $this->option('write')) {
            $this->warn('Для проверки добавьте --write. Будет создан и удалён только тестовый объект.');

            return self::FAILURE;
        }

        $path = ProductMedia::REMOTE_PREFIX.'storage-check-'.Str::uuid().'.png';
        $content = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j4i8AAAAASUVORK5CYII=', true);
        $created = false;
        $disk = null;
        $step = 'подключение';
        $result = self::FAILURE;

        try {
            $disk = Storage::disk('media');
            $step = 'запись';

            if (! $disk->put($path, $content, ['ContentType' => 'image/png', 'CacheControl' => 'no-store'])) {
                throw new RuntimeException;
            }

            $created = true;
            $this->info('Запись: OK.');
            $step = 'чтение приложением';

            if ($disk->get($path) !== $content) {
                throw new RuntimeException;
            }

            $this->info('Чтение приложением и целостность: OK.');
            $step = 'публичное чтение';
            $response = Http::connectTimeout(5)->timeout(15)->withoutRedirecting()->get($disk->url($path));
            $this->line('Анонимный GET: HTTP '.$response->status().'.');

            if (! $response->ok() || $response->body() !== $content) {
                throw new RuntimeException;
            }

            $this->info('Публичное чтение и целостность: OK.');
            $result = self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Проверка не пройдена: '.$step.'. Тип ошибки: '.class_basename($exception));
        } finally {
            if ($created && $disk !== null) {
                try {
                    if (! $disk->delete($path) || $disk->exists($path)) {
                        throw new RuntimeException;
                    }

                    $this->info('Тестовый объект удалён.');
                } catch (Throwable) {
                    $this->error('Не удалось удалить тестовый объект: '.$path);
                    $result = self::FAILURE;
                }
            }
        }

        return $result;
    }
}
