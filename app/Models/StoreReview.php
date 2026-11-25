<?php

namespace App\Models;

use App\Enums\StoreReviewSource;
use Database\Factories\StoreReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreReview extends Model
{
    /** @use HasFactory<StoreReviewFactory> */
    use HasFactory;

    protected $fillable = ['author', 'source', 'rating', 'body', 'reviewed_at', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['source' => StoreReviewSource::class, 'rating' => 'integer', 'reviewed_at' => 'date', 'sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderByDesc('id');
    }
}
