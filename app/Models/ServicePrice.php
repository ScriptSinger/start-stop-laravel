<?php

namespace App\Models;

use Database\Factories\ServicePriceFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Цена обслуживания аккумулятора для диапазона ёмкости, Ач
 * (таблица на странице «Обслуживание»).
 */
class ServicePrice extends Model
{
    /** @use HasFactory<ServicePriceFactory> */
    use HasFactory;

    protected $fillable = [
        'capacity_from',
        'capacity_to',
        'price',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'capacity_from' => 'integer',
            'capacity_to' => 'integer',
            'price' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('capacity_from');
    }

    /**
     * Подпись ёмкости: «35-65 Ah».
     */
    public function capacityLabel(): string
    {
        return "{$this->capacity_from}-{$this->capacity_to} Ah";
    }
}
