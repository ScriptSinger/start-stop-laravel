<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Prohibitable;

/**
 * Базовая команда импорта из старого проекта. Импорт перезаписывает товары,
 * категории, страницы и т.д. данными OpenCart, поэтому после запуска сайта
 * он должен быть закрыт: иначе случайный запуск затрёт правки менеджеров.
 *
 * Запрет ставится в AppServiceProvider по LEGACY_IMPORT_ENABLED (см.
 * config/shop.php): на production по умолчанию импорт выключен.
 */
abstract class LegacyImportCommand extends Command
{
    use Prohibitable;

    final public function handle(): int
    {
        if ($this->isProhibited(quiet: true)) {
            $this->components->error('Импорт из старого проекта выключен: он перезапишет данные, отредактированные в админке.');
            $this->line('  Для разового импорта (например, при запуске сайта) задайте LEGACY_IMPORT_ENABLED=true в .env, а после — уберите.');

            return self::FAILURE;
        }

        return $this->import();
    }

    abstract protected function import(): int;
}
