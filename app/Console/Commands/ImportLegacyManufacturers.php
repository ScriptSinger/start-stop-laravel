<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesLegacySlug;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('import:legacy-manufacturers')]
#[Description('Импорт производителей из oc_manufacturer старого проекта')]
class ImportLegacyManufacturers extends Command
{
    use ResolvesLegacySlug;

    public function handle(): int
    {
        $rows = DB::connection('legacy')->table('oc_manufacturer')->get();

        $this->withProgressBar($rows, function ($row): void {
            DB::table('manufacturers')->updateOrInsert(
                ['id' => $row->manufacturer_id],
                [
                    'name' => $row->name,
                    'slug' => $this->resolveSlug("manufacturer_id={$row->manufacturer_id}", $row->name, 'manufacturers', $row->manufacturer_id),
                    'image' => $row->image ?: null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        });

        $this->newLine(2);
        $this->info("Импортировано производителей: {$rows->count()}");

        return self::SUCCESS;
    }
}
