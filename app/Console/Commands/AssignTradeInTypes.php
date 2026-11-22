<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Catalog\TradeInTypeMatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('trade-in:assign-types {--force : Записать; без флага — только отчёт}')]
#[Description('Назначить товарам тип корпуса (поле «Тип корпуса») по их «Габаритам»: по коду в названии или по диапазону размеров')]
class AssignTradeInTypes extends Command
{
    /**
     * Только добавляет: товарам, у которых тип корпуса ещё не задан. Заданные вручную
     * типы не меняются. Что не подобралось (размеры вне диапазонов, два подходящих
     * типа, нет габаритов), остаётся пустым — тип ставит менеджер в карточке товара
     * или через Excel. Перед запуском: php artisan db:backup.
     */
    public function handle(TradeInTypeMatcher $matcher): int
    {
        $dimensionsAttribute = (int) config('shop.battery_fitment.attributes.dimensions');

        $found = collect();
        $unmatched = collect();

        Product::query()
            ->whereNull('trade_in_type_id')
            ->whereHas('attributeValues', fn ($values) => $values->where('attribute_values.attribute_id', $dimensionsAttribute))
            ->with('attributeValues')
            ->lazyById()
            ->each(function (Product $product) use ($matcher, $found, $unmatched, $dimensionsAttribute): void {
                $type = $matcher->typeForProduct($product);

                if ($type !== null) {
                    $found->push([$product, $type]);

                    return;
                }

                if ($product->status) {
                    $unmatched->push($product->attributeValues->firstWhere('attribute_id', $dimensionsAttribute)->value);
                }
            });

        $active = $found->filter(fn (array $item): bool => $item[0]->status);

        $this->components->twoColumnDetail('Товаров без типа, у которых тип подобрался', (string) $found->count());
        $this->components->twoColumnDetail('…из них активных', (string) $active->count());
        $this->components->twoColumnDetail('Активных с габаритами, у которых тип не подобрался', (string) $unmatched->count());
        $this->components->twoColumnDetail('Активных аккумуляторов совсем без типа корпуса сейчас', (string) $this->activeWithoutType());

        $this->table(['Тип', 'Скидка, руб.', 'Товаров'], $found->groupBy(fn (array $item): string => $item[1]->code)
            ->map(fn (Collection $group): array => [$group->first()[1]->name, (int) $group->first()[1]->amount, $group->count()])
            ->values()->all());

        $top = $unmatched->countBy()->sortDesc()->take(15);

        if ($top->isNotEmpty()) {
            $this->line('Габариты, которые не попали ни в один тип (частые):');
            $this->table(['Габариты', 'Товаров'], $top->map(fn (int $count, string $name): array => [$name, $count])->values()->all());
        }

        if (! $this->option('force')) {
            $this->warn('Пробный прогон: ничего не записано. Для записи добавьте --force.');

            return self::SUCCESS;
        }

        $found->each(fn (array $item): bool => $item[0]->update(['trade_in_type_id' => $item[1]->id]));

        $this->info("Готово: тип корпуса назначен товарам — {$found->count()}.");

        return self::SUCCESS;
    }

    private function activeWithoutType(): int
    {
        return Product::query()
            ->where('status', true)
            ->whereNull('trade_in_type_id')
            ->whereHas('categories', fn ($categories) => $categories->whereIn('categories.id', config('shop.battery_fitment.category_ids')))
            ->count();
    }
}
