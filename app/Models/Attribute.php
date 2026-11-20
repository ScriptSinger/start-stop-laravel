<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    protected $fillable = [
        'name',
        'hint',
        'guide_id',
        'guide_anchor',
        'sort_order',
        'display_sort_order',
        'is_filterable',
        'show_on_card',
    ];

    protected $casts = [
        'is_filterable' => 'boolean',
        'show_on_card' => 'boolean',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * Показывать покупателю на карточке и в сравнении. Пока миграция не
     * выполнена, колонки нет — показываем всё, а не роняем страницу.
     */
    public function showsOnCard(): bool
    {
        return (bool) ($this->show_on_card ?? true);
    }

    public function guide(): BelongsTo
    {
        return $this->belongsTo(Guide::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('sort_order')->orderBy('value');
    }
}
