<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Catalog\ProductDuplicates;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('products:delete-duplicates {--force : Удалить; без флага — только отчёт} {--report= : Путь к CSV с планом по каждому товару группы}')]
#[Description('Найти дубли товаров (одинаковое название) и удалить их мягко; спорные группы только показать')]
class DeleteDuplicateProducts extends Command
{
    /**
     * Без --force ничего не удаляет. Удаление мягкое: товары остаются в базе и
     * видны в админке на вкладке «Удалённые», откуда их можно восстановить.
     * Спорные группы (разная цена, sku и т.п.) не трогаются, они попадают в
     * отчёт. Правила — в ProductDuplicates. Перед запуском: php artisan db:backup.
     */
    public function handle(ProductDuplicates $duplicates): int
    {
        $groups = $duplicates->groups();
        [$disputed, $safe] = $groups->partition(fn (array $group): bool => $group['reasons'] !== []);
        $toDelete = $safe->flatMap(fn (array $group): Collection => $group['duplicates']);

        $this->report($groups, $safe, $disputed, $toDelete);

        if ($this->option('report')) {
            $this->writeReport($this->option('report'), $safe, $disputed);
        }

        if (! $this->option('force')) {
            $this->warn('Пробный прогон: ничего не удалено. Для удаления добавьте --force.');

            return self::SUCCESS;
        }

        DB::transaction(fn () => $toDelete->each(fn (Product $product): ?bool => $product->delete()));

        $this->info("Удалено товаров: {$toDelete->count()}. Они видны в админке на вкладке «Удалённые».");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, array{key: string, keep: Product, duplicates: Collection<int, Product>, reasons: list<string>}>  $groups
     * @param  Collection<int, array{key: string, keep: Product, duplicates: Collection<int, Product>, reasons: list<string>}>  $safe
     * @param  Collection<int, array{key: string, keep: Product, duplicates: Collection<int, Product>, reasons: list<string>}>  $disputed
     * @param  Collection<int, Product>  $toDelete
     */
    private function report(Collection $groups, Collection $safe, Collection $disputed, Collection $toDelete): void
    {
        $this->components->twoColumnDetail('Групп дублей', (string) $groups->count());
        $this->components->twoColumnDetail('Безопасных групп (будет удалено)', (string) $safe->count());
        $this->components->twoColumnDetail('Товаров к удалению', (string) $toDelete->count());
        $this->components->twoColumnDetail('Спорных групп (не трогаем)', (string) $disputed->count());

        $reasons = $disputed->flatMap(fn (array $group): array => $group['reasons'])->countBy();

        foreach ($reasons as $reason => $count) {
            $this->components->twoColumnDetail("  {$reason}", (string) $count);
        }

        if ($safe->isNotEmpty()) {
            $this->line('Примеры (первые 8 безопасных групп):');
            $this->table(['Остаётся', 'Название', 'Удаляется (id)'], $safe->take(8)->map(fn (array $group): array => [
                $group['keep']->id,
                mb_strimwidth($group['keep']->name, 0, 45, '…'),
                $group['duplicates']->pluck('id')->implode(', '),
            ])->all());
        }
    }

    /**
     * @param  Collection<int, array{key: string, keep: Product, duplicates: Collection<int, Product>, reasons: list<string>}>  $safe
     * @param  Collection<int, array{key: string, keep: Product, duplicates: Collection<int, Product>, reasons: list<string>}>  $disputed
     */
    private function writeReport(string $path, Collection $safe, Collection $disputed): void
    {
        $handle = fopen($path, 'w');

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['id', 'Название', 'Цена', 'Статус', 'sku', 'external_id', 'Действие', 'Причина'], ';', '"', '');

        foreach ([[$safe, 'удалить'], [$disputed, 'проверить (спорная группа)']] as [$groups, $action]) {
            foreach ($groups as $group) {
                foreach ([$group['keep'], ...$group['duplicates']] as $product) {
                    fputcsv($handle, [
                        $product->id,
                        $product->name,
                        $product->price,
                        $product->status ? 'активен' : 'отключён',
                        $product->sku,
                        $product->external_id,
                        $product->is($group['keep']) ? 'оставить' : $action,
                        implode('; ', $group['reasons']),
                    ], ';', '"', '');
                }
            }
        }

        fclose($handle);

        $this->line("План по каждому товару: {$path}");
    }
}
