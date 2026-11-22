<?php

namespace App\Services\Cart;

use App\Models\Product;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Корзина в сессии хранит количество, трейд-ин и снимок цен для предупреждения.
 * Сумма заказа всегда рассчитывается по текущим данным товара.
 */
class Cart
{
    private const SESSION_KEY = 'cart';

    public const MAX_QUANTITY = 99;

    public function __construct(private readonly Session $session, private readonly CustomerCartStore $customerCarts) {}

    public function add(Product $product, int $quantity = 1, bool $tradeIn = false): void
    {
        $this->mutate(function (array &$items) use ($product, $quantity, $tradeIn): void {
            $current = $items[$product->id]['quantity'] ?? 0;
            $items[$product->id] = [
                'quantity' => $this->clampQuantity($current + $quantity),
                'trade_in' => $tradeIn && $product->hasTradeIn(),
                'prices' => $items[$product->id]['prices'] ?? $this->prices($product),
            ];
        });
    }

    public function update(Product $product, int $quantity, bool $tradeIn, ?string $expected = null): bool
    {
        if ($quantity <= 0) {
            return $this->remove($product, $expected);
        }

        return $this->mutate(function (array &$items) use ($product, $quantity, $tradeIn, $expected): bool {
            if (! $this->matchesItem($items[$product->id] ?? null, $expected)) {
                return false;
            }
            if (! isset($items[$product->id])) {
                return true;
            }
            $items[$product->id] = [
                'quantity' => $this->clampQuantity($quantity),
                'trade_in' => $tradeIn && $product->hasTradeIn(),
                'prices' => $items[$product->id]['prices'] ?? $this->prices($product),
            ];

            return true;
        });
    }

    public function remove(Product $product, ?string $expected = null): bool
    {
        return $this->mutate(function (array &$items) use ($product, $expected): bool {
            if (! $this->matchesItem($items[$product->id] ?? null, $expected)) {
                return false;
            }
            unset($items[$product->id]);

            return true;
        });
    }

    public function clear(): void
    {
        $this->mutate(function (array &$items): void {
            $items = [];
        });
    }

    /**
     * Строки корзины. Товары, которые сняли с продажи или удалили, пока они
     * лежали в корзине, не включаются в строки.
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
            ->with('tradeInType')
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

    private function matchesItem(?array $item, ?string $expected): bool
    {
        return $expected === null || ($item !== null && $expected === $item['quantity'].':'.(int) $item['trade_in']);
    }

    /**
     * @param  Collection<int, CartLine>  $lines
     */
    public function unavailableCount(Collection $lines): int
    {
        return max(0, count($this->rawItems()) - $lines->count());
    }

    public function discardUnavailable(): void
    {
        $this->mutate(function (array &$items): void {
            $ids = Product::query()->whereIn('id', array_keys($items))->where('status', true)->pluck('id')->all();
            $items = array_intersect_key($items, array_fill_keys($ids, true));
        });
    }

    /**
     * @param  Collection<int, CartLine>  $lines
     */
    public function hasPriceChanges(Collection $lines): bool
    {
        $items = $this->rawItems();

        foreach ($lines as $line) {
            $previous = $items[$line->product->id]['prices'] ?? null;
            $key = $line->tradeIn ? 'trade_in' : 'regular';
            if (is_array($previous) && isset($previous[$key]) && $previous[$key] !== $this->prices($line->product)[$key]) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, CartLine>  $lines
     */
    public function rememberPrices(Collection $lines): void
    {
        $this->mutate(function (array &$items) use ($lines): void {
            foreach ($lines as $line) {
                if (isset($items[$line->product->id])) {
                    $items[$line->product->id]['prices'] = $this->prices($line->product);
                }
            }
        });
    }

    /** @return array{regular: string, trade_in: string} */
    private function prices(Product $product): array
    {
        return [
            'regular' => number_format($product->priceFor(false), 2, '.', ''),
            'trade_in' => number_format($product->priceFor(true), 2, '.', ''),
        ];
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
     * @return array<int, array{quantity: int, trade_in: bool, prices?: array{regular: string, trade_in: string}}>
     */
    private function rawItems(): array
    {
        $customerId = Auth::guard('web')->id();
        if ($customerId !== null) {
            return $this->customerCarts->transact((int) $customerId, fn (array &$items): array => $items);
        }
        $items = $this->session->get(self::SESSION_KEY, []);

        return is_array($items) ? $items : [];
    }

    /**
     * @param  array<int, array{quantity: int, trade_in: bool, prices?: array{regular: string, trade_in: string}}>  $items
     */
    private function save(array $items): void
    {
        $this->session->put(self::SESSION_KEY, $items);
        if (! $this->session->has('cart_guest_token')) {
            $this->session->put('cart_guest_token', (string) Str::uuid());
        }
    }

    private function mutate(callable $callback): mixed
    {
        $customerId = Auth::guard('web')->id();
        if ($customerId !== null) {
            return $this->customerCarts->transact((int) $customerId, $callback);
        }
        $items = $this->rawItems();
        $result = $callback($items);
        $this->save($items);

        return $result;
    }

    private function clampQuantity(int $quantity): int
    {
        return max(1, min($quantity, self::MAX_QUANTITY));
    }
}
