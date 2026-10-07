<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('catalog:cleanup-categories {--force : Записать изменения; без флага — только отчёт}')]
#[Description('Убрать лишние привязки товаров к разделам (товар уже в подразделе) и категорию-ярлык «Акции»')]
class CleanupCategoryLinks extends Command
{
    /**
     * Наследие OpenCart: раздел показывал только свои товары, поэтому товар
     * привязывали и к разделу, и к подразделу. Теперь раздел показывает
     * подразделы сам (Category::treeIds()) — привязка к разделу лишняя.
     * «Акции» — выключенная категория-ярлык: акции ведутся ценой по акции
     * и подборкой на главной. Перед запуском: php artisan db:backup.
     */
    public function handle(): int
    {
        $redundant = $this->redundantParentLinks();
        $promo = Category::query()->where('slug', 'aktsii')->first();
        $promoLinks = $promo ? DB::table('category_product')->where('category_id', $promo->id)->pluck('product_id') : collect();
        // Товар, у которого «Акции» — единственная категория, без неё остался бы без категории.
        $promoOnly = $promoLinks->filter(fn (int $productId): bool => DB::table('category_product')->where('product_id', $productId)->count() === 1);

        $this->table(['', 'Сколько'], [
            ['Лишних привязок к разделу (товар уже в подразделе)', $redundant->count()],
            ['Привязок к категории «Акции»', $promoLinks->count()],
            ['…из них у товаров, где «Акции» — единственная категория (оставляем)', $promoOnly->count()],
        ]);

        $redundant->countBy('pair')->sortDesc()->each(fn (int $count, string $pair) => $this->line("  {$pair}: {$count}"));

        if (! $this->option('force')) {
            $this->warn('Пробный прогон: база не изменена. Для записи добавьте --force.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($redundant, $promo, $promoOnly): void {
            $redundant->each(fn (array $link) => DB::table('category_product')
                ->where('product_id', $link['product_id'])
                ->where('category_id', $link['category_id'])
                ->delete());

            if ($promo !== null) {
                DB::table('category_product')
                    ->where('category_id', $promo->id)
                    ->whereNotIn('product_id', $promoOnly->all())
                    ->delete();

                if ($promoOnly->isEmpty()) {
                    $promo->delete();
                }
            }
        });

        $this->info('Готово.'.($promo && $promoOnly->isNotEmpty() ? ' Категория «Акции» оставлена: в ней товары без другой категории.' : ''));

        return self::SUCCESS;
    }

    /**
     * Привязки товара к категории, когда он привязан и к её включённой
     * подкатегории (любой глубины) — раздел покажет его и так.
     *
     * @return Collection<int, array{product_id: int, category_id: int, pair: string}>
     */
    private function redundantParentLinks(): Collection
    {
        $categories = Category::query()->get()->keyBy('id');

        return DB::table('category_product')->get()
            ->groupBy('product_id')
            ->flatMap(fn (Collection $links, int $productId): Collection => $links
                ->filter(function (object $link) use ($links, $categories): bool {
                    $category = $categories->get($link->category_id);
                    $descendants = array_diff($category?->treeIds() ?? [], [$link->category_id]);

                    return $links->pluck('category_id')->intersect($descendants)->isNotEmpty();
                })
                ->map(fn (object $link): array => [
                    'product_id' => $productId,
                    'category_id' => $link->category_id,
                    'pair' => $categories->get($link->category_id)->name.' → '.$links->pluck('category_id')
                        ->intersect($categories->get($link->category_id)->treeIds())
                        ->reject(fn (int $id): bool => $id === $link->category_id)
                        ->map(fn (int $id): string => $categories->get($id)->name)
                        ->implode(', '),
                ]));
    }
}
