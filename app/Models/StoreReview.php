<?php

namespace App\Models;

use App\Enums\StoreReviewSource;
use Database\Factories\StoreReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class StoreReview extends Model
{
    /** @use HasFactory<StoreReviewFactory> */
    use HasFactory;

    protected $fillable = ['author', 'source', 'rating', 'body', 'reviewed_at', 'sort_order', 'is_active', 'avatar_url', 'photo_urls', 'avatar_path', 'photo_paths', 'photo_preview_paths', 'source_external_id', 'import_fingerprint'];

    protected function casts(): array
    {
        return ['source' => StoreReviewSource::class, 'rating' => 'integer', 'reviewed_at' => 'date', 'sort_order' => 'integer', 'is_active' => 'boolean', 'photo_urls' => 'array', 'photo_paths' => 'array', 'photo_preview_paths' => 'array'];
    }

    public function avatarDisplayUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('media')->url($this->avatar_path) : $this->avatar_url;
    }

    public function photoDisplayUrls(): array
    {
        return $this->photo_paths
            ? array_map(fn (string $path): string => Storage::disk('media')->url($path), $this->photo_paths)
            : array_map(static fn (string $url): string => preg_replace('~^(https://avatars\.mds\.yandex\.net/get-altay/[A-Za-z0-9_-]+/[A-Za-z0-9_-]+/)[A-Za-z0-9_-]+(?:\?[^#]*)?$~D', '$1orig', $url), $this->photo_urls ?? []);
    }

    public function photoPreviewUrls(): array
    {
        if (! $this->photo_paths) {
            return $this->photo_urls ?? [];
        }
        if (count($this->photo_preview_paths ?? []) !== count($this->photo_paths)) {
            return $this->photoDisplayUrls();
        }

        return array_map(fn (string $path): string => Storage::disk('media')->url($path), $this->photo_preview_paths);
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
