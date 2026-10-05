<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\DecodesLegacyText;
use App\Console\Commands\Concerns\ResolvesLegacySlug;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Facades\DB;

#[Signature('import:legacy-categories')]
#[Description('Импорт категорий из oc_category/oc_category_description старого проекта')]
class ImportLegacyCategories extends LegacyImportCommand
{
    use DecodesLegacyText;
    use ResolvesLegacySlug;

    protected function import(): int
    {
        $rows = DB::connection('legacy')
            ->table('oc_category as c')
            ->join('oc_category_description as cd', 'cd.category_id', '=', 'c.category_id')
            ->where('cd.language_id', 1)
            ->select('c.*', 'cd.name', 'cd.description', 'cd.meta_h1', 'cd.meta_title', 'cd.meta_description')
            ->get();

        // Сначала все категории без parent_id — родитель может идти после
        // ребёнка в выборке, а parent_id ссылается на ещё не вставленную запись.
        $this->withProgressBar($rows, function ($row): void {
            DB::table('categories')->updateOrInsert(
                ['id' => $row->category_id],
                [
                    'name' => $this->legacyText($row->name),
                    'slug' => $this->resolveSlug("category_id={$row->category_id}", $this->legacyText($row->name), 'categories', $row->category_id),
                    'description' => $this->legacyText($row->description),
                    // H1 храним, только если он отличается от названия.
                    'heading' => trim((string) $this->legacyText($row->meta_h1)) !== trim((string) $this->legacyText($row->name)) ? $this->legacyText($row->meta_h1) : null,
                    'meta_title' => $this->legacyText($row->meta_title),
                    'meta_description' => $this->legacyText($row->meta_description),
                    'image' => $row->image ?: null,
                    'sort_order' => $row->sort_order,
                    'status' => (bool) $row->status,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        });

        $this->newLine();

        // Второй проход — проставляем parent_id, когда все строки уже на месте.
        foreach ($rows as $row) {
            if ((int) $row->parent_id === 0) {
                continue;
            }

            DB::table('categories')
                ->where('id', $row->category_id)
                ->update(['parent_id' => $row->parent_id]);
        }

        $this->info("Импортировано категорий: {$rows->count()}");

        // Категории-бренды старого проекта сразу переводим в производителей —
        // иначе отдельный запуск этой команды сбросил бы бренды товаров.
        $this->call('import:legacy-brands');

        return self::SUCCESS;
    }
}
