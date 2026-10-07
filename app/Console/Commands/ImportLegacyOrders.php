<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\DecodesLegacyText;
use App\Enums\OrderStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Facades\DB;

#[Signature('import:legacy-orders')]
#[Description('Импорт клиентов и заказов (с позициями) из старого проекта')]
class ImportLegacyOrders extends LegacyImportCommand
{
    use DecodesLegacyText;

    protected function import(): int
    {
        $this->importCustomers();
        $this->importOrders();

        return self::SUCCESS;
    }

    private function importCustomers(): void
    {
        $rows = DB::connection('legacy')->table('oc_customer')->get();

        $this->withProgressBar($rows, function ($row): void {
            $hasOwnPassword = DB::table('customers')->where('id', $row->customer_id)->whereNotNull('password')->exists();

            DB::table('customers')->updateOrInsert(
                ['id' => $row->customer_id],
                [
                    'name' => trim("{$row->firstname} {$row->lastname}"),
                    'email' => $row->email ? mb_strtolower(trim($row->email)) : "customer-{$row->customer_id}@legacy.invalid",
                    'phone' => $row->telephone ?: null,
                    'updated_at' => now(),
                    'created_at' => $row->date_added,
                    // Старый пароль (sha1 с солью) проверяется при первом входе и
                    // пересохраняется обычным хешем (Customer::upgradeLegacyPassword).
                    // Кто уже задал пароль на новом сайте — того не трогаем.
                    ...($hasOwnPassword ? [] : [
                        'password' => null,
                        'legacy_password_hash' => $row->password ?: null,
                        'legacy_password_salt' => $row->salt ?: null,
                    ]),
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

        // Трейд-ин в позициях: выбранная опция «Цена при обмене» → её скидка.
        // Цена позиции в OpenCart уже включает эту скидку.
        $tradeInDiscounts = DB::connection('legacy')
            ->table('oc_order_option as oo')
            ->join('oc_product_option_value as pov', 'pov.product_option_value_id', '=', 'oo.product_option_value_id')
            ->where('pov.price_prefix', '-')
            ->pluck('pov.price', 'oo.order_product_id');

        $this->withProgressBar($rows, function ($row) use ($knownCustomerIds, $knownProductIds, $statusNames, $tradeInDiscounts): void {
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
                    'status' => OrderStatus::fromLegacy($statusNames[$row->order_status_id] ?? null)->value,
                    'payment_method' => $this->methodLabel($row->payment_method),
                    'delivery_method' => $this->methodLabel($row->shipping_method),
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
                    'trade_in_discount' => $tradeInDiscounts[$item->order_product_id] ?? null,
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

    /**
     * Подпись способа оплаты/доставки из заказа OpenCart: закодирована в
     * HTML-сущности и с <br> («Самовывоз: Магазин &quot;СТАРТ-СТОП&quot;<br>Время
     * работы: 10-20»). Плейсхолдер «Выберите … для этого заказа» — не способ.
     */
    private function methodLabel(?string $value): ?string
    {
        $label = trim(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], ', ', (string) $this->legacyText($value))));

        return $label === '' || str_starts_with($label, 'Выберите') ? null : $label;
    }
}
