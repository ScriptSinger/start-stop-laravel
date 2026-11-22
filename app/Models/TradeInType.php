<?php

namespace App\Models;

use App\Services\Catalog\TradeInTypeMatcher;
use Database\Factories\TradeInTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Тип корпуса и скидка за сдачу старого АКБ. Тип — диапазон размеров в мм (или
 * код в названии «Габаритов», как у грузовых Груз A/B/C) нужен для подбора типа
 * товару; скидку получают товары, у которых этот тип записан в поле «Тип корпуса».
 */
class TradeInType extends Model
{
    /** @use HasFactory<TradeInTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'amount',
        'length_min',
        'length_max',
        'width_min',
        'width_max',
        'height_min',
        'height_max',
        'sort_order',
        'is_active',
    ];

    protected static function booted(): void
    {
        $flush = fn () => app(TradeInTypeMatcher::class)->flush();

        static::saved($flush);
        static::deleted($flush);
    }

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Типы, которые применяются и показываются на сайте.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function hasDimensionRange(): bool
    {
        return $this->length_min !== null && $this->length_max !== null
            && $this->width_min !== null && $this->width_max !== null
            && $this->height_min !== null && $this->height_max !== null;
    }

    /**
     * Размеры (длина, ширина, высота, мм) попадают в диапазон типа.
     */
    public function containsDimensions(int $length, int $width, int $height): bool
    {
        return $this->hasDimensionRange()
            && $length >= $this->length_min && $length <= $this->length_max
            && $width >= $this->width_min && $width <= $this->width_max
            && $height >= $this->height_min && $height <= $this->height_max;
    }
}
