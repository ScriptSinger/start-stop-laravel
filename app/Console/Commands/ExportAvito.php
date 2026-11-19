<?php

namespace App\Console\Commands;

use App\Services\Catalog\AvitoExport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('avito:export {--disk=public : Диск для файла (public, s3 и т. д.)} {--path=avito/avito.xlsx : Путь к файлу на диске}')]
#[Description('Выгрузить товары в наличии и под заказ в XLSX для Авито')]
class ExportAvito extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AvitoExport $export): int
    {
        $disk = (string) $this->option('disk');
        $path = (string) $this->option('path');

        try {
            $count = $export->store($disk, $path);
        } catch (Throwable $exception) {
            report($exception);
            $this->error("Выгрузка не удалась: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Выгружено товаров: {$count}. Файл: {$disk}:{$path}");

        return self::SUCCESS;
    }
}
