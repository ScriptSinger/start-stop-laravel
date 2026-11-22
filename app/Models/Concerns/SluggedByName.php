<?php

namespace App\Models\Concerns;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Support\Str;

/**
 * Адрес (slug) из названия, если он не задан. Заданный вручную slug и slug
 * существующей записи не меняются: адреса уже в индексе поисковиков.
 * Дубли получают суффикс (-2, -3…).
 */
trait SluggedByName
{
    use Sluggable;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
                'method' => fn (string $string, string $separator): string => Str::slug(strtr($string, ['щ' => 'shch', 'Щ' => 'Shch']), $separator, 'ru') ?: (string) Str::uuid(),
            ],
        ];
    }
}
