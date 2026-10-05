<?php

namespace App\Actions;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Services\Cart\CartLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Размещение заказа с сайта. Цены — из строк корзины, т.е. посчитанные
 * заново из товаров; в заказ пишется их снимок (название, цена, скидка).
 */
class PlaceOrder
{
    /**
     * @param  Collection<int, CartLine>  $lines
     * @param  array{name: string, phone: string, email: ?string, address: ?string, comment: ?string}  $contact
     */
    public function handle(Collection $lines, DeliveryMethod $delivery, PaymentMethod $payment, array $contact): Order
    {
        return DB::transaction(function () use ($lines, $delivery, $payment, $contact): Order {
            $order = Order::query()->create([
                'customer_name' => $contact['name'],
                'customer_phone' => $contact['phone'],
                'customer_email' => $contact['email'],
                'status' => 'new',
                'payment_method' => $payment->label(),
                'delivery_method' => $delivery->label(),
                'shipping_address' => $delivery->needsAddress() ? $contact['address'] : null,
                'comment' => $contact['comment'],
                'total' => $lines->sum(fn (CartLine $line): float => $line->total()) + $delivery->price(),
            ]);

            $order->items()->createMany($lines->map(fn (CartLine $line): array => [
                'product_id' => $line->product->id,
                'name' => $line->product->name,
                'price' => $line->unitPrice(),
                'trade_in_discount' => $line->tradeInDiscount(),
                'quantity' => $line->quantity,
                'total' => $line->total(),
            ])->all());

            return $order;
        });
    }
}
