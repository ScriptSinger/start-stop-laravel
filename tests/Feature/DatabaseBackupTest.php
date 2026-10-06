<?php

namespace Tests\Feature;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/backups');
        File::deleteDirectory($this->directory);

        config([
            'backup.path' => $this->directory,
            'backup.keep_days' => 14,
            'database.connections.mysql' => [
                'driver' => 'mysql',
                'host' => 'db',
                'port' => 3306,
                'database' => 'shop',
                'username' => 'user',
                'password' => 'secret pass',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_backup_dumps_database_with_password_in_environment_and_verifies_it(): void
    {
        Process::fake(fn (PendingProcess $process) => $this->writeDumpFile($process));

        $this->travelTo('2026-10-06 02:30:00');

        $this->artisan('db:backup', ['--label' => 'Перед правкой'])
            ->expectsOutputToContain('Копия сохранена и проверена')
            ->assertSuccessful();

        Process::assertRan(fn (PendingProcess $process): bool => str_contains($process->command[2], 'mariadb-dump')
            && str_contains($process->command[2], '--single-transaction')
            && $process->environment['MYSQL_PWD'] === 'secret pass'
            && ! str_contains($process->command[2], 'secret pass')
            && $process->environment['BACKUP_FILE'] === $this->directory.'/shop-2026-10-06_023000-pered-pravkoi.sql.gz');
        Process::assertRan(fn (PendingProcess $process): bool => str_contains($process->command[2], 'Dump completed'));
    }

    public function test_failed_dump_is_removed_and_reported(): void
    {
        Process::fake(function (PendingProcess $process) {
            $this->writeDumpFile($process);

            return Process::result(errorOutput: 'Access denied', exitCode: 2);
        });

        $this->artisan('db:backup')
            ->expectsOutputToContain('Дамп не снят: Access denied')
            ->assertFailed();

        $this->assertSame([], File::glob($this->directory.'/*.sql.gz'));
    }

    public function test_incomplete_dump_is_rejected(): void
    {
        Process::fake([
            '*' => Process::sequence()
                ->push(Process::result())
                ->push(Process::result(exitCode: 1)),
        ]);

        $this->artisan('db:backup')
            ->expectsOutputToContain('Дамп оборвался')
            ->assertFailed();
    }

    public function test_old_backups_are_pruned_but_newest_is_kept(): void
    {
        Process::fake(fn (PendingProcess $process) => $this->writeDumpFile($process));
        File::ensureDirectoryExists($this->directory);
        touch($old = $this->directory.'/shop-old.sql.gz', now()->subDays(20)->getTimestamp());
        touch($recent = $this->directory.'/shop-recent.sql.gz', now()->subDays(3)->getTimestamp());

        $this->artisan('db:backup')
            ->expectsOutputToContain('Удалена устаревшая копия: shop-old.sql.gz')
            ->assertSuccessful();

        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($recent);
        $this->assertCount(2, File::glob($this->directory.'/*.sql.gz'));
    }

    public function test_restore_asks_confirmation_and_backs_up_current_state_first(): void
    {
        Process::fake(fn (PendingProcess $process) => $this->writeDumpFile($process));
        File::ensureDirectoryExists($this->directory);
        File::put($this->directory.'/shop-good.sql.gz', 'dump');

        $this->artisan('db:restore', ['file' => 'shop-good.sql.gz'])
            ->expectsConfirmation('Текущие данные базы будут заменены данными из shop-good.sql.gz. Продолжить?', 'no')
            ->expectsOutput('Отменено.')
            ->assertSuccessful();

        Process::assertNothingRan();

        $this->artisan('db:restore', ['file' => 'shop-good.sql.gz', '--force' => true])
            ->expectsOutputToContain('-before-restore.sql.gz')
            ->expectsOutput('База восстановлена из shop-good.sql.gz.')
            ->assertSuccessful();

        Process::assertRan(fn (PendingProcess $process): bool => str_contains($process->command[2], '| mariadb ')
            && $process->environment['BACKUP_FILE'] === $this->directory.'/shop-good.sql.gz');
    }

    public function test_restore_refuses_unknown_file(): void
    {
        Process::fake();

        $this->artisan('db:restore', ['file' => 'missing.sql.gz', '--force' => true])
            ->expectsOutput('Файл missing.sql.gz не найден.')
            ->assertFailed();

        Process::assertNothingRan();
    }

    /**
     * Фейковый mariadb-dump: создаёт файл, куда команда ждёт дамп.
     */
    private function writeDumpFile(PendingProcess $process): mixed
    {
        if (isset($process->environment['BACKUP_FILE']) && str_contains($process->command[2], 'mariadb-dump')) {
            File::put($process->environment['BACKUP_FILE'], 'dump');
        }

        return Process::result();
    }
}
