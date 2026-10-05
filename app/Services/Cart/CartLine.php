<?php

namespace App\Services\Cart;

use App\Models\Product;

/**
 * Строка корзины с ценой, посчитанной заново из товара (не из сессии).
 */
final readonly class CartLine
{
    public function __construct(
        public Product $product,
        public int $quantity,
        public bool $tradeIn,
    ) {}

    public function unitPrice(): float
    {
        return $this->product->priceFor($this->tradeIn);
    }

    public function tradeInDiscount(): ?float
    {
        return $this->tradeIn && $this->product->hasTradeIn()
            ? (float) $this->product->trade_in_discount
            : null;
    }

    public function total(): float
    {
        return $this->unitPrice() * $this->quantity;
    }

    /**
     * «в наличии» / «под заказ» / «уточним срок» — купить можно в любом случае,
     * как и на старом сайте (config_stock_checkout = 1).
     */
    public function availability(): string
    {
        return match (true) {
            $this->product->quantity > 0 => 'in_stock',
            $this->product->isAvailableOnOrder() => 'on_order',
            default => 'out_of_stock',
        };
    }
}
