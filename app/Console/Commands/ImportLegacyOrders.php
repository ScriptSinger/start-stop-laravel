<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('import:legacy-orders')]
#[Description('Импорт клиентов и заказов (с позициями) из старого проекта')]
class ImportLegacyOrders extends Command
{
    public function handle(): int
    {
        $this->importCustomers();
        $this->importOrders();

        return self::SUCCESS;
    }

    private function importCustomers(): void
    {
        $rows = DB::connection('legacy')->table('oc_customer')->get();

        $this->withProgressBar($rows, function ($row): void {
            DB::table('customers')->updateOrInsert(
                ['id' => $row->customer_id],
                [
                    'name' => trim("{$row->firstname} {$row->lastname}"),
                    'email' => $row->email ?: "customer-{$row->customer_id}@legacy.invalid",
                    'phone' => $row->telephone ?: null,
                    // Пароль не переносим — старый хэш (соль + тройной sha1) не
                    // совместим с Laravel bcrypt, клиентам потребуется сброс.
                    'password' => null,
                    'updated_at' => now(),
                    'created_at' => $row->date_added,
                ],
            );
        });

        $this->newLine(2);
        $this->info("Импортировано клиентов: {$rows->count()}");
    }

    private function importOrders(): void
    {
        $knownCustomerIds = DB::table('customers')->pluck('id')->all();
        $knownProductIds = DB::table('products')->pluck('id')->all();
        $statusNames = DB::connection('legacy')
            ->table('oc_order_status')
            ->where('language_id', 1)
            ->pluck('name', 'order_status_id');

        $rows = DB::connection('legacy')->table('oc_order')->get();

        $this->withProgressBar($rows, function ($row) use ($knownCustomerIds, $knownProductIds, $statusNames): void {
            $customerId = in_array($row->customer_id, $knownCustomerIds, true)
                ? $row->customer_id
                : null;

            DB::table('orders')->updateOrInsert(
                ['id' => $row->order_id],
                [
                    'customer_id' => $customerId,
                    'customer_name' => trim("{$row->firstname} {$row->lastname}"),
                    'customer_phone' => $row->telephone ?: null,
                    'customer_email' => $row->email ?: null,
                    'status' => $statusNames[$row->order_status_id] ?? 'unknown',
                    'payment_method' => $row->payment_method ?: null,
                    'total' => $row->total,
                    'shipping_address' => trim("{$row->shipping_address_1} {$row->shipping_address_2} {$row->shipping_city}"),
                    'updated_at' => $row->date_modified,
                    'created_at' => $row->date_added,
                ],
            );

            DB::table('order_items')->where('order_id', $row->order_id)->delete();

            $items = DB::connection('legacy')
                ->table('oc_order_product')
                ->where('order_id', $row->order_id)
                ->get();

            foreach ($items as $item) {
                DB::table('order_items')->insert([
                    'order_id' => $row->order_id,
                    'product_id' => in_array($item->product_id, $knownProductIds, true) ? $item->product_id : null,
                    'name' => $item->name,
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                    'total' => $item->total,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $this->newLine(2);
        $this->info("Импортировано заказов: {$rows->count()}");
    }
}
