<?php

namespace App\Models;

use App\Enums\MenuLocation;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $fillable = [
        'location',
        'parent_id',
        'title',
        'url',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'location' => MenuLocation::class,
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Активные пункты верхнего уровня меню с подпунктами — для витрины.
     */
    #[Scope]
    protected function forLocation(Builder $query, MenuLocation $location): void
    {
        $query->where('location', $location)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with('children')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Ссылка для href: внутренние пути — через url(), внешние — как есть.
     */
    public function href(): ?string
    {
        if ($this->url === null || $this->url === '') {
            return null;
        }

        return str_starts_with($this->url, 'http') ? $this->url : url($this->url);
    }
}
