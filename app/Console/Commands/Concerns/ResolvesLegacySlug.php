<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait ResolvesLegacySlug
{
    /**
     * Берём готовый keyword из oc_seo_url старого проекта как стартовый slug —
     * меньше работы, не нужно генерировать заново. Сайт сейчас не проиндексирован,
     * так что совпадение 1:1 со старыми URL не требование, только стартовые данные.
     */
    protected function resolveSlug(string $query, string $fallbackName, string $table): string
    {
        $keyword = DB::connection('legacy')
            ->table('oc_seo_url')
            ->where('query', $query)
            ->where('store_id', 0)
            ->where('language_id', 1)
            ->value('keyword');

        $slug = $keyword ?: Str::slug($fallbackName);

        if ($slug === '') {
            $slug = (string) Str::uuid();
        }

        $original = $slug;
        $i = 2;

        while (DB::table($table)->where('slug', $slug)->exists()) {
            $slug = $original.'-'.$i++;
        }

        return $slug;
    }
}
