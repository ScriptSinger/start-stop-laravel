<?php

namespace App\Models;

use App\Models\Concerns\HasOnlineStatus;
use MoonShine\Laravel\Models\MoonshineUser as BaseMoonshineUser;

/**
 * Администратор MoonShine с отметкой присутствия (last_seen_at).
 */
class MoonshineUser extends BaseMoonshineUser
{
    use HasOnlineStatus;

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }
}
