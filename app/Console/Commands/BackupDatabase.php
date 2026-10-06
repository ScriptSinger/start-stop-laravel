<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('db:backup {--label= : Пометка в имени файла, например before-manufacturers}')]
#[Description('Снять резервную копию базы магазина, проверить её и удалить устаревшие копии')]
class BackupDatabase extends Command
{
    /**
     * Запускается каждую ночь (routes/console.php) и вручную — перед любой
     * массовой правкой данных: php artisan db:backup --label=что-делаем.
     */
    public function handle(DatabaseBackup $backup): int
    {
        try {
            $file = $backup->create($this->option('label'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Копия сохранена и проверена: '.$file.' ('.$this->megabytes(filesize($file)).')');

        foreach ($backup->prune(config('backup.keep_days')) as $deleted) {
            $this->line('Удалена устаревшая копия: '.basename($deleted));
        }

        return self::SUCCESS;
    }

    /**
     * Number::fileSize() требует расширение intl, которого в образе нет.
     */
    private function megabytes(int $bytes): string
    {
        return number_format($bytes / 1024 / 1024, 1, ',', ' ').' МБ';
    }
}
