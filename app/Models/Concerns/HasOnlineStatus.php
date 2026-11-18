<?php

namespace App\Models\Concerns;

use App\Enums\OnlineStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;

/**
 * Присутствие по колонке last_seen_at: покупатели сайта и администраторы MoonShine.
 *
 * @property Carbon|null $last_seen_at
 */
trait HasOnlineStatus
{
    public const ONLINE_MINUTES = 5;

    /**
     * Флаг для админки: MoonShine Switcher показывает его зелёной или красной точкой.
     *
     * @return Attribute<bool, never>
     */
    protected function isOnline(): Attribute
    {
        return Attribute::get(fn (): bool => $this->status === OnlineStatus::Online);
    }

    /**
     * Статус по времени последнего визита.
     *
     * @return Attribute<OnlineStatus, never>
     */
    protected function status(): Attribute
    {
        return Attribute::get(fn (): OnlineStatus => OnlineStatus::fromLastSeen($this->last_seen_at, self::ONLINE_MINUTES));
    }
}
