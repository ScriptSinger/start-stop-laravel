<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('import:legacy-battery')]
#[Description('Импорт базы подбора АКБ из oc_battery_base старого проекта')]
class ImportLegacyBattery extends Command
{
    public function handle(): int
    {
        $rows = DB::connection('legacy')->table('oc_battery_base')->get();

        $this->withProgressBar($rows, function ($row): void {
            DB::table('battery_fitments')->updateOrInsert(
                ['id' => $row->id],
                [
                    'brand' => $row->brand,
                    'model' => $row->model,
                    'generation' => $row->generation ?: null,
                    'capacity' => $row->capacity ?: null,
                    'polarity' => $row->polarity ?: null,
                    'dims' => $row->dims ?: null,
                    // У всех 16082 строк legacy это один и тот же мёртвый
                    // placeholder 'catalog/cars/no_image.jpg', которого не
                    // существовало даже в старом проекте — не фото конкретной
                    // машины, а литерал без информации. Реальный подбор фото по
                    // generation делал battery_filter.php динамически, его
                    // переносим отдельно вместе с виджетом подбора (Фаза 3/4),
                    // не здесь.
                    'image' => $row->image === 'catalog/cars/no_image.jpg' ? null : $row->image,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        });

        $this->newLine(2);
        $this->info("Импортировано записей базы АКБ: {$rows->count()}");

        return self::SUCCESS;
    }
}
