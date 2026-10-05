<?php

namespace App\Models;

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

    /**
     * Статусы заказа: ключ хранится в orders.status (для импортированных —
     * название статуса из oc_order_status), значение — подпись в админке.
     * "unknown" — order_status_id = 0 в OpenCart: оформление брошено на
     * полпути, старая админка такие заказы вообще не показывала.
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        'new' => 'Новый',
        'Ожидание' => 'Ожидание',
        'В обработке' => 'В обработке',
        'Обработано' => 'Обработано',
        'Сделка завершена' => 'Сделка завершена',
        'Отменено' => 'Отменено',
        'unknown' => 'Не оформлен (брошен)',
    ];

    protected $casts = [
        'total' => 'decimal:4',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
