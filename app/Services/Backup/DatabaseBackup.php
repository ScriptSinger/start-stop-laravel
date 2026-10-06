<?php

namespace App\Services\Backup;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Дампы базы магазина: mariadb-dump со сжатием, проверка целостности,
 * ротация старых копий и восстановление.
 *
 * Параметры подключения передаются через переменные окружения процесса,
 * а не аргументами: пароль не попадает в список процессов, а имена файлов
 * и базы — в shell без экранирования.
 */
class DatabaseBackup
{
    public function __construct(
        private readonly string $connection = 'mysql',
    ) {}

    /**
     * Снять дамп и проверить его. Возвращает путь к файлу.
     */
    public function create(?string $label = null): string
    {
        File::ensureDirectoryExists($this->directory());

        $file = $this->directory().'/'.$this->fileName($label);

        $result = Process::env([...$this->credentials(), 'BACKUP_FILE' => $file])
            ->timeout(1800)
            ->run([
                'bash', '-c',
                'set -o pipefail; mariadb-dump --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" '
                .'--single-transaction --quick --routines --triggers --no-tablespaces '
                .'--default-character-set=utf8mb4 "$DB_NAME" | gzip > "$BACKUP_FILE"',
            ]);

        if ($result->failed()) {
            File::delete($file);

            throw new RuntimeException('Дамп не снят: '.trim($result->errorOutput()));
        }

        if (! $this->isComplete($file)) {
            File::delete($file);

            throw new RuntimeException('Дамп оборвался: в конце файла нет отметки о завершении.');
        }

        return $file;
    }

    /**
     * Архив читается целиком и заканчивается строкой «-- Dump completed»,
     * которую mariadb-dump пишет только при успешном завершении.
     */
    public function isComplete(string $file): bool
    {
        return Process::env(['BACKUP_FILE' => $file])
            ->run(['bash', '-c', 'set -o pipefail; gzip -t "$BACKUP_FILE" && gzip -dc "$BACKUP_FILE" | tail -n 1 | grep -q "Dump completed"'])
            ->successful();
    }

    /**
     * Удалить копии старше срока хранения. Самую свежую не трогаем никогда.
     *
     * @return list<string> удалённые файлы
     */
    public function prune(int $keepDays): array
    {
        $backups = $this->all();
        $threshold = now()->subDays($keepDays);

        return $backups
            ->skip(1)
            ->filter(fn (array $backup): bool => $backup['created_at']->lt($threshold))
            ->each(fn (array $backup): bool => File::delete($backup['path']))
            ->pluck('path')
            ->values()
            ->all();
    }

    /**
     * Копии от новых к старым.
     *
     * @return Collection<int, array{path: string, name: string, size: int, created_at: Carbon}>
     */
    public function all(): Collection
    {
        if (! File::isDirectory($this->directory())) {
            return collect();
        }

        return collect(File::glob($this->directory().'/*.sql.gz'))
            ->map(fn (string $path): array => [
                'path' => $path,
                'name' => basename($path),
                'size' => File::size($path),
                'created_at' => Carbon::createFromTimestamp(File::lastModified($path)),
            ])
            ->sortByDesc('created_at')
            ->values();
    }

    /**
     * Залить дамп в базу магазина вместо текущих данных.
     */
    public function restore(string $file): void
    {
        if (! $this->isComplete($file)) {
            throw new RuntimeException('Файл повреждён или неполный — восстанавливать из него нельзя.');
        }

        $result = Process::env([...$this->credentials(), 'BACKUP_FILE' => $file])
            ->timeout(1800)
            ->run([
                'bash', '-c',
                'set -o pipefail; gzip -dc "$BACKUP_FILE" | mariadb --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" '
                .'--default-character-set=utf8mb4 "$DB_NAME"',
            ]);

        if ($result->failed()) {
            throw new RuntimeException('Восстановление не удалось: '.trim($result->errorOutput()));
        }
    }

    /**
     * Файл из папки копий по имени или путь к файлу.
     */
    public function resolve(string $file): string
    {
        $path = File::exists($file) ? $file : $this->directory().'/'.basename($file);

        if (! File::exists($path)) {
            throw new RuntimeException("Файл {$file} не найден.");
        }

        return $path;
    }

    public function directory(): string
    {
        return rtrim((string) config('backup.path'), '/');
    }

    private function fileName(?string $label): string
    {
        $suffix = filled($label) ? '-'.Str::slug($label) : '';

        return config("database.connections.{$this->connection}.database").'-'.now()->format('Y-m-d_His').$suffix.'.sql.gz';
    }

    /**
     * @return array<string, string>
     */
    private function credentials(): array
    {
        $config = config("database.connections.{$this->connection}");

        if (! in_array($config['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Резервное копирование настроено только для MariaDB/MySQL.');
        }

        return [
            'DB_HOST' => (string) $config['host'],
            'DB_PORT' => (string) $config['port'],
            'DB_USER' => (string) $config['username'],
            'DB_NAME' => (string) $config['database'],
            'MYSQL_PWD' => (string) $config['password'],
        ];
    }
}
