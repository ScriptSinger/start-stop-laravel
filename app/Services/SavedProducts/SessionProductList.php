<?php

namespace App\Services\SavedProducts;

use App\Models\Product;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Collection;

/**
 * Список товаров в сессии (закладки, сравнение) — без регистрации, как
 * гостевые закладки темы UniShop2. Хранятся только id в порядке добавления.
 */
abstract class SessionProductList
{
    public function __construct(private readonly Session $session) {}

    abstract protected function sessionKey(): string;

    /**
     * Максимум товаров; при переполнении вытесняется самый старый. null — без ограничения.
     */
    protected function limit(): ?int
    {
        return null;
    }

    public function add(Product $product): void
    {
        $ids = array_values(array_diff($this->ids(), [$product->id]));
        $ids[] = $product->id;

        if ($this->limit() !== null && count($ids) > $this->limit()) {
            $ids = array_slice($ids, -$this->limit());
        }

        $this->session->put($this->sessionKey(), $ids);
    }

    public function remove(Product $product): void
    {
        $this->session->put($this->sessionKey(), array_values(array_diff($this->ids(), [$product->id])));
    }

    public function has(Product $product): bool
    {
        return in_array($product->id, $this->ids(), true);
    }

    /**
     * Активные товары списка в порядке добавления (снятые с продажи выпадают).
     *
     * @return Collection<int, Product>
     */
    public function products(): Collection
    {
        $ids = $this->ids();

        if ($ids === []) {
            return new Collection;
        }

        return Product::query()
            ->whereIn('id', $ids)
            ->where('status', true)
            ->withCardData()
            ->get()
            ->sortBy(fn (Product $product): int => array_search($product->id, $ids, true))
            ->values();
    }

    public function count(): int
    {
        return $this->ids() === [] ? 0 : $this->products()->count();
    }

    /**
     * @return list<int>
     */
    private function ids(): array
    {
        $ids = $this->session->get($this->sessionKey(), []);

        return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
    }
}
