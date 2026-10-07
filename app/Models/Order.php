<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'customer_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'status',
        'payment_method',
        'delivery_method',
        'total',
        'shipping_address',
        'comment',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'total' => 'decimal:4',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Заказы, которые видит покупатель: брошенное оформление (unknown) не показываем.
     */
    #[Scope]
    protected function visibleToCustomer(Builder $query): void
    {
        $query->where('status', '!=', OrderStatus::Abandoned);
    }

    /**
     * Заказы с этим способом получения. В заказе хранится текст подписи,
     * а у старых заказов он свой («Бесплатная доставка» — это тоже доставка
     * по городу), поэтому ищем по началу «Самовывоз».
     */
    #[Scope]
    protected function withDelivery(Builder $query, DeliveryMethod $method): void
    {
        $method === DeliveryMethod::Pickup
            ? $query->where('delivery_method', 'like', 'Самовывоз%')
            : $query->where('delivery_method', 'not like', 'Самовывоз%');
    }

    /**
     * Заказы с этим способом оплаты — по ключевому слову в подписи;
     * «Оплата при доставке» со старого сайта считается наличными.
     */
    #[Scope]
    protected function withPayment(Builder $query, PaymentMethod $method): void
    {
        match ($method) {
            PaymentMethod::Cash => $query->where(fn (Builder $query): Builder => $query
                ->where('payment_method', 'like', '%наличн%')
                ->orWhere('payment_method', 'like', '%при доставке%')),
            PaymentMethod::Transfer => $query->where('payment_method', 'like', '%перевод%'),
            PaymentMethod::Card => $query->where('payment_method', 'like', '%картой%'),
        };
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
