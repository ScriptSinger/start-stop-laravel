<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('db:restore {file? : Имя файла из папки копий или путь к нему; без него — список копий} {--force : Не спрашивать подтверждение}')]
#[Description('Восстановить базу магазина из резервной копии (текущие данные будут заменены)')]
class RestoreDatabase extends Command
{
    /**
     * Перед заменой снимается копия текущего состояния: если восстановили
     * не тот файл, можно вернуться обратно.
     */
    public function handle(DatabaseBackup $backup): int
    {
        if (! $this->argument('file')) {
            $this->table(['Файл', 'Размер', 'Создан'], $backup->all()->map(fn (array $item): array => [
                $item['name'],
                $this->megabytes($item['size']),
                $item['created_at']->format('d.m.Y H:i'),
            ]));

            return self::SUCCESS;
        }

        try {
            $file = $backup->resolve($this->argument('file'));

            if (! $this->option('force') && ! $this->confirm('Текущие данные базы будут заменены данными из '.basename($file).'. Продолжить?')) {
                $this->line('Отменено.');

                return self::SUCCESS;
            }

            $safety = $backup->create('before-restore');
            $this->line('Копия текущего состояния: '.basename($safety));

            $backup->restore($file);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('База восстановлена из '.basename($file).'.');

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
