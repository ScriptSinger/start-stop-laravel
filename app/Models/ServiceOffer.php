<?php

namespace App\Models;

use App\Enums\ServicePriceType;
use Database\Factories\ServiceOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Карточка услуги на странице «Услуги». Цена — тип (ServicePriceType) плюс
 * сумма для «фикс» и «от»; terms — необязательное продолжение подписи.
 */
class ServiceOffer extends Model
{
    /** @use HasFactory<ServiceOfferFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'icon',
        'description',
        'price_type',
        'price',
        'terms',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_type' => ServicePriceType::class,
            'price' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Услуги, которые показываются на сайте.
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

    /**
     * Строка условий на карточке: подпись типа цены и, если есть, текст условий
     * («Бесплатно» + «при покупке аккумулятора» → «Бесплатно при покупке аккумулятора»).
     */
    public function priceLabel(): string
    {
        return trim($this->price_type->label($this->price).' '.$this->terms);
    }
}
