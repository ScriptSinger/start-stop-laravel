<?php

namespace App\Models;

use App\Models\Concerns\HasHtmlDescription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasHtmlDescription;

    protected $fillable = [
        'parent_id',
        'name',
        'heading',
        'meta_title',
        'meta_description',
        'slug',
        'description',
        'image',
        'icon',
        'home_wall_sort',
        'show_on_parent_wall',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'show_on_parent_wall' => 'boolean',
    ];

    /**
     * Иконка задана классом Font Awesome ("fas fa-tools"), а не картинкой.
     */
    public function hasFontIcon(): bool
    {
        return $this->icon !== null && preg_match('/^fa[srlbd]?\s/', $this->icon) === 1;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Не attributes(): это имя занято свойством Eloquent-модели.
     */
    public function productAttributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class);
    }

    /**
     * Производители-ссылки на плитке раздела в «Популярных категориях».
     */
    public function wallManufacturers(): BelongsToMany
    {
        return $this->belongsToMany(Manufacturer::class, 'category_wall_manufacturer')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }
}
