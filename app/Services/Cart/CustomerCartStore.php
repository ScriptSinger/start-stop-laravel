<?php

namespace App\Services\Cart;

use App\Models\Customer;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;

class CustomerCartStore
{
    public function __construct(private readonly Session $session) {}

    public function mergeAccounts(int $sourceId, int $destinationId): void
    {
        $source = DB::table('customer_carts')->where('customer_id', $sourceId)->value('items');
        if ($source === null) {
            return;
        }

        DB::table('customer_carts')->insertOrIgnore([
            'customer_id' => $destinationId,
            'items' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $destination = DB::table('customer_carts')->where('customer_id', $destinationId)->value('items');
        $items = json_decode($destination, true, flags: JSON_THROW_ON_ERROR);
        foreach (json_decode($source, true, flags: JSON_THROW_ON_ERROR) as $productId => $line) {
            if (isset($items[$productId])) {
                $line['quantity'] = min(Cart::MAX_QUANTITY, $items[$productId]['quantity'] + $line['quantity']);
            }
            $items[$productId] = $line;
        }
        DB::table('customer_carts')->where('customer_id', $destinationId)->update([
            'items' => json_encode($items, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }

    /**
     * @template T
     *
     * @param  callable(array<int, array<string, mixed>>&): T  $callback
     * @return T
     */
    public function transact(int $customerId, callable $callback): mixed
    {
        $guest = $this->session->get('cart', []);
        $token = hash('sha256', (string) $this->session->get('cart_guest_token', $this->session->getId()));

        $result = DB::transaction(function () use ($customerId, $callback, $guest, $token): mixed {
            Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();
            DB::table('customer_carts')->insertOrIgnore([
                'customer_id' => $customerId,
                'items' => '[]',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $row = DB::table('customer_carts')->where('customer_id', $customerId)->first();
            $items = json_decode($row->items, true, flags: JSON_THROW_ON_ERROR);

            if (is_array($guest) && $guest !== [] && DB::table('customer_cart_merges')->insertOrIgnore([
                'token' => $token,
                'customer_id' => $customerId,
                'created_at' => now(),
                'updated_at' => now(),
            ])) {
                foreach ($guest as $productId => $line) {
                    if (isset($items[$productId])) {
                        $line['quantity'] = min(Cart::MAX_QUANTITY, $items[$productId]['quantity'] + $line['quantity']);
                    }
                    $items[$productId] = $line;
                }
            }

            $result = $callback($items);
            DB::table('customer_carts')->where('customer_id', $customerId)->update([
                'items' => json_encode($items, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

            return $result;
        }, attempts: 3);

        $this->session->forget(['cart', 'cart_guest_token']);

        return $result;
    }
}
