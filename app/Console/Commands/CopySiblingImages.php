<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Catalog\ProductImageSiblings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('products:copy-sibling-images {--force : Записать; без флага — только отчёт} {--report= : Путь к CSV: какой товар какое фото получит}')]
#[Description('Подставить фото товарам из 1С без фото по «родственным» товарам сайта (та же серия АКБ, то же название с другими цифрами)')]
class CopySiblingImages extends Command
{
    /**
     * Без --force ничего не пишет. Затрагивает только товары с кодом 1С без фото;
     * существующие фото не меняются. Файлы не копируются: у товаров один путь к
     * общему файлу, как у серий аккумуляторов на сайте. Перед запуском: php artisan db:backup.
     */
    public function handle(ProductImageSiblings $siblings): int
    {
        $plan = $siblings->plan();
        $withoutImage = Product::query()->whereNotNull('external_id')->where(fn ($query) => $query->whereNull('image')->orWhere('image', ''))->count();

        $this->components->twoColumnDetail('Товаров из 1С без фото', (string) $withoutImage);
        $this->components->twoColumnDetail('Получат фото по аналогии', (string) $plan->count());

        foreach ($plan->groupBy('rule') as $rule => $group) {
            $this->components->twoColumnDetail("  {$rule}", (string) $group->count());
        }

        $this->components->twoColumnDetail('Останутся без фото', (string) ($withoutImage - $plan->count()));

        $this->table(['Товар', 'Фото взято у'], $plan->take(10)->map(fn (array $item): array => [
            mb_strimwidth($item['product']->name, 0, 45, '…'),
            mb_strimwidth($item['source']->name, 0, 45, '…'),
        ])->all());

        if ($this->option('report')) {
            $this->writeReport($this->option('report'), $plan);
        }

        if (! $this->option('force')) {
            $this->warn('Пробный прогон: фото не подставлены. Для записи добавьте --force.');

            return self::SUCCESS;
        }

        $this->info('Фото подставлено товарам: '.$siblings->apply($plan).'.');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, array{product: Product, image: string, source: Product, rule: string}>  $plan
     */
    private function writeReport(string $path, Collection $plan): void
    {
        $handle = fopen($path, 'w');

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['id', 'Товар', 'Код 1С', 'Фото', 'Взято у товара (id)', 'Название источника', 'Правило'], ';', '"', '');

        foreach ($plan as $item) {
            fputcsv($handle, [$item['product']->id, $item['product']->name, $item['product']->external_id, $item['image'], $item['source']->id, $item['source']->name, $item['rule']], ';', '"', '');
        }

        fclose($handle);

        $this->line("План по каждому товару: {$path}");
    }
}
