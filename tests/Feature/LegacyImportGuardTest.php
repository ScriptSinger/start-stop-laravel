<?php

namespace Tests\Feature;

use App\Console\Commands\LegacyImportCommand;
use App\Providers\AppServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LegacyImportGuardTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function importCommands(): array
    {
        return [
            'полный импорт' => ['import:legacy'],
            'товары' => ['import:legacy-products'],
            'категории' => ['import:legacy-categories'],
            'бренды' => ['import:legacy-brands'],
            'страницы' => ['import:legacy-pages'],
        ];
    }

    #[DataProvider('importCommands')]
    public function test_import_refuses_to_run_when_disabled(string $command): void
    {
        config(['shop.legacy_import_enabled' => false]);
        (new AppServiceProvider($this->app))->boot();

        $this->artisan($command)
            ->expectsOutputToContain('Импорт из старого проекта выключен')
            ->assertFailed();
    }

    protected function tearDown(): void
    {
        // Запрет — статическое свойство: не даём ему протечь в другие тесты.
        LegacyImportCommand::prohibit(false);

        parent::tearDown();
    }
}
