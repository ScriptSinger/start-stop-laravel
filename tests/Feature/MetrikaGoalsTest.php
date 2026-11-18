<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetrikaGoalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_success_page_hands_the_order_to_metrika_once_per_order(): void
    {
        $product = Product::query()->create(['name' => 'TITAN 60Ah О.П.', 'slug' => 'titan-60', 'price' => 7000, 'quantity' => 3, 'status' => true]);

        $this->post(route('cart.store', $product), ['quantity' => 2]);
        $this->post(route('checkout.store'), ['name' => 'Иван', 'phone' => '+7 (987) 000-00-00', 'agree' => 1, 'delivery' => 'pickup', 'payment' => 'cash'])
            ->assertRedirect(route('checkout.success'));

        $order = Order::query()->sole();

        $html = $this->get(route('checkout.success'))->assertOk()->getContent();

        preg_match('/data-metrika-purchase="([^"]*)"/', $html, $matches);
        $purchase = json_decode(html_entity_decode($matches[1]), true);

        $this->assertSame($order->id, $purchase['id']);
        $this->assertEquals(14000, $purchase['revenue']);
        $this->assertEquals([['id' => (string) $product->id, 'name' => 'TITAN 60Ah О.П.', 'price' => 7000, 'quantity' => 2]], $purchase['products']);
    }

    public function test_modal_forms_carry_their_metrika_goal(): void
    {
        $product = Product::query()->create(['name' => 'ZUBR 60', 'slug' => 'zubr-60', 'price' => 5000, 'quantity' => 1, 'status' => true]);

        $this->get(route('callback.create'))->assertSee('data-metrika-goal="callback"', false);
        $this->get(route('quick-order.create', $product))->assertSee('data-metrika-goal="quick_order"', false);
        $this->get(route('product-question.create', $product))->assertSee('data-metrika-goal="product_question"', false);
    }
}
