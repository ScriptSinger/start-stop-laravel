<?php

namespace App\Services\Cart;

use App\Models\Product;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Корзина в сессии: только id товара, количество и галочка трейд-ина.
 * Цены всегда берутся из товара — подменить их через сессию нельзя.
 */
class Cart
{
    private const SESSION_KEY = 'cart';

    public const MAX_QUANTITY = 99;

    public function __construct(private readonly Session $session) {}

    public function add(Product $product, int $quantity = 1, bool $tradeIn = false): void
    {
        $items = $this->rawItems();
        $current = $items[$product->id]['quantity'] ?? 0;

        $items[$product->id] = [
            'quantity' => $this->clampQuantity($current + $quantity),
            'trade_in' => $tradeIn && $product->hasTradeIn(),
        ];

        $this->save($items);
    }

    public function update(Product $product, int $quantity, bool $tradeIn): void
    {
        if ($quantity <= 0) {
            $this->remove($product);

            return;
        }

        $items = $this->rawItems();

        if (! isset($items[$product->id])) {
            return;
        }

        $items[$product->id] = [
            'quantity' => $this->clampQuantity($quantity),
            'trade_in' => $tradeIn && $product->hasTradeIn(),
        ];

        $this->save($items);
    }

    public function remove(Product $product): void
    {
        $items = $this->rawItems();
        unset($items[$product->id]);

        $this->save($items);
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    /**
     * Строки корзины. Товары, которые сняли с продажи или удалили, пока они
     * лежали в корзине, молча выпадают.
     *
     * @return Collection<int, CartLine>
     */
    public function lines(): Collection
    {
        $items = $this->rawItems();

        if ($items === []) {
            return collect();
        }

        return Product::query()
            ->whereIn('id', array_keys($items))
            ->where('status', true)
            ->get()
            ->map(fn (Product $product): CartLine => new CartLine(
                $product,
                $items[$product->id]['quantity'],
                $items[$product->id]['trade_in'],
            ))
            ->values();
    }

    /**
     * Сколько штук в корзине — по тем же строкам, что видны в корзине
     * (без товаров, снятых с продажи).
     */
    public function count(): int
    {
        return $this->isEmpty()
            ? 0
            : $this->lines()->sum(fn (CartLine $line): int => $line->quantity);
    }

    public function isEmpty(): bool
    {
        return $this->rawItems() === [];
    }

    /**
     * @param  Collection<int, CartLine>|null  $lines
     */
    public function total(?Collection $lines = null): float
    {
        return ($lines ?? $this->lines())->sum(fn (CartLine $line): float => $line->total());
    }

    /**
     * Если в корзине есть товар «только самовывоз», доставки нет для всего заказа.
     *
     * @param  Collection<int, CartLine>|null  $lines
     */
    public function isPickupOnly(?Collection $lines = null): bool
    {
        return ($lines ?? $this->lines())->contains(fn (CartLine $line): bool => $line->product->is_pickup_only);
    }

    /**
     * @return array<int, array{quantity: int, trade_in: bool}>
     */
    private function rawItems(): array
    {
        $items = $this->session->get(self::SESSION_KEY, []);

        return is_array($items) ? $items : [];
    }

    /**
     * @param  array<int, array{quantity: int, trade_in: bool}>  $items
     */
    private function save(array $items): void
    {
        $this->session->put(self::SESSION_KEY, $items);
    }

    private function clampQuantity(int $quantity): int
    {
        return max(1, min($quantity, self::MAX_QUANTITY));
    }
}
