<?php

namespace Tests\Feature;

use App\Actions\PlaceOrder;
use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Models\Product;
use App\Services\Cart\CartLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlaceOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_places_order_with_snapshot_of_lines(): void
    {
        $battery = Product::query()->create(['name' => 'TITAN 60Ah', 'slug' => 'titan', 'price' => 7000, 'trade_in_discount' => 1000]);
        $terminal = Product::query()->create(['name' => 'Клемма', 'slug' => 'klemma', 'price' => 500]);

        $order = app(PlaceOrder::class)->handle(
            collect([new CartLine($battery, 2, tradeIn: true), new CartLine($terminal, 1, tradeIn: false)]),
            DeliveryMethod::Pickup,
            PaymentMethod::Card,
            ['name' => 'Иван', 'phone' => '+79870000000', 'email' => null, 'address' => 'не нужен', 'comment' => null],
        );

        $this->assertSame('new', $order->status);
        $this->assertSame(DeliveryMethod::Pickup->label(), $order->delivery_method);
        $this->assertSame(PaymentMethod::Card->label(), $order->payment_method);
        $this->assertNull($order->shipping_address);
        $this->assertEquals(12500, $order->total);

        // Снимок: переименование товара не меняет заказ.
        $battery->update(['name' => 'TITAN 60Ah (новое название)', 'price' => 9000]);
        $item = $order->items()->where('product_id', $battery->id)->sole();
        $this->assertSame('TITAN 60Ah', $item->name);
        $this->assertEquals(6000, $item->price);
        $this->assertEquals(1000, $item->trade_in_discount);
    }

    /**
     * @return array<string, array{PaymentMethod, DeliveryMethod, bool}>
     */
    public static function paymentRules(): array
    {
        return [
            'картой при самовывозе' => [PaymentMethod::Card, DeliveryMethod::Pickup, true],
            'картой с доставкой' => [PaymentMethod::Card, DeliveryMethod::City, false],
            'наличными с доставкой' => [PaymentMethod::Cash, DeliveryMethod::City, true],
            'переводом с доставкой' => [PaymentMethod::Transfer, DeliveryMethod::City, true],
        ];
    }

    #[DataProvider('paymentRules')]
    public function test_card_payment_only_for_pickup(PaymentMethod $payment, DeliveryMethod $delivery, bool $allowed): void
    {
        $this->assertSame($allowed, $payment->isAllowedFor($delivery));
    }
}
