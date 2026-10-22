<?php

namespace App\Console\Commands;

use App\Models\Attribute;
use App\Models\Product;
use App\Services\Catalog\TradeInDiscount;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('products:fill-trade-in {--force : Записать; без флага — только отчёт}')]
#[Description('Проставить скидку за обмен АКБ скрытым аккумуляторам без неё по ёмкости (config shop.trade_in_by_capacity)')]
class FillBatteryTradeIn extends Command
{
    /**
     * Только скрытые (status = 0) аккумуляторы легковых и грузовых разделов без
     * скидки, у которых известна ёмкость (характеристика «Емкость»). Активные
     * товары и товары с уже заданной скидкой не меняются. Перед запуском: php artisan db:backup.
     */
    public function handle(TradeInDiscount $tradeIn): int
    {
        $capacityAttribute = (int) config('shop.battery_fitment.capacity_exact_attribute');

        if (! Attribute::query()->whereKey($capacityAttribute)->exists()) {
            $this->error('На сайте нет характеристики «Емкость».');

            return self::FAILURE;
        }

        $products = Product::query()
            ->where('status', false)
            ->where(fn ($query) => $query->whereNull('trade_in_discount')->orWhere('trade_in_discount', '<=', 0))
            ->whereHas('categories', fn ($query) => $query->whereIn('categories.id', config('shop.battery_fitment.category_ids')))
            ->with(['attributeValues' => fn ($query) => $query->where('attribute_values.attribute_id', $capacityAttribute)])
            ->get()
            ->map(fn (Product $product): array => [
                'product' => $product,
                'capacity' => $this->capacity($product),
            ]);

        $plan = $products->filter(fn (array $item): bool => $item['capacity'] !== null)
            ->map(fn (array $item): array => [...$item, 'discount' => $tradeIn->forCapacity($item['capacity'])]);

        $this->components->twoColumnDetail('Скрытых АКБ без скидки', (string) $products->count());
        $this->components->twoColumnDetail('…с известной ёмкостью (получат скидку)', (string) $plan->count());
        $this->components->twoColumnDetail('…без ёмкости (остаются без скидки)', (string) ($products->count() - $plan->count()));

        $this->table(['Скидка, руб.', 'Товаров'], $plan->groupBy('discount')->map(fn (Collection $group, int $discount): array => [$discount, $group->count()])->sortKeys()->values()->all());

        if (! $this->option('force')) {
            $this->warn('Пробный прогон: скидки не проставлены. Для записи добавьте --force.');

            return self::SUCCESS;
        }

        $plan->each(fn (array $item): bool => $item['product']->update(['trade_in_discount' => $item['discount']]));

        $this->info("Проставлена скидка за обмен: {$plan->count()}.");

        return self::SUCCESS;
    }

    private function capacity(Product $product): ?float
    {
        $value = $product->attributeValues->first()?->value;

        return $value !== null && preg_match('/\d+(?:[.,]\d+)?/', $value, $match) === 1 ? (float) str_replace(',', '.', $match[0]) : null;
    }
}
