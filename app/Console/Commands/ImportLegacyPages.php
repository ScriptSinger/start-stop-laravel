<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesLegacySlug;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('import:legacy-pages')]
#[Description('Импорт статических страниц (О компании, Услуги и т.д.) из oc_information старого проекта')]
class ImportLegacyPages extends Command
{
    use ResolvesLegacySlug;

    public function handle(): int
    {
        $rows = DB::connection('legacy')
            ->table('oc_information as i')
            ->join('oc_information_description as d', 'd.information_id', '=', 'i.information_id')
            ->where('d.language_id', 1)
            ->select('i.*', 'd.title', 'd.description')
            ->get();

        $this->withProgressBar($rows, function ($row): void {
            DB::table('pages')->updateOrInsert(
                ['id' => $row->information_id],
                [
                    'title' => $row->title,
                    'slug' => $this->resolveSlug("information_id={$row->information_id}", $row->title, 'pages'),
                    'description' => $row->description ?: null,
                    'show_in_top' => (bool) $row->bottom,
                    'sort_order' => $row->sort_order,
                    'status' => (bool) $row->status,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        });

        $this->newLine(2);
        $this->info("Импортировано страниц: {$rows->count()}");

        return self::SUCCESS;
    }
}
