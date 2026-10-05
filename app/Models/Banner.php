<?php

namespace App\Models;

use App\Enums\BannerPosition;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Banner extends Model
{
    protected $fillable = [
        'position',
        'title',
        'image',
        'url',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'position' => BannerPosition::class,
        'is_active' => 'boolean',
    ];

    /**
     * Активные баннеры места в порядке показа.
     */
    #[Scope]
    protected function shownAt(Builder $query, BannerPosition $position): void
    {
        $query->where('position', $position)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image);
    }

    public function href(): ?string
    {
        if ($this->url === null || $this->url === '') {
            return null;
        }

        return str_starts_with($this->url, 'http') ? $this->url : url($this->url);
    }
}
