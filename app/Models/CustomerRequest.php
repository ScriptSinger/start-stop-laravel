<?php

namespace App\Models;

use App\Enums\CustomerRequestType;
use App\Jobs\UpdateCustomerRequestTelegramMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRequest extends Model
{
    protected static function booted(): void
    {
        static::updated(function (CustomerRequest $customerRequest): void {
            if ($customerRequest->wasChanged('is_processed') && $customerRequest->telegram_message_id !== null) {
                UpdateCustomerRequestTelegramMessage::dispatch($customerRequest->id)->afterCommit();
            }
        });
    }

    protected $fillable = [
        'type',
        'name',
        'phone',
        'email',
        'product_id',
        'comment',
        'admin_comment',
        'is_processed',
    ];

    protected $casts = [
        'type' => CustomerRequestType::class,
        'is_processed' => 'boolean',
        'telegram_message_id' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
