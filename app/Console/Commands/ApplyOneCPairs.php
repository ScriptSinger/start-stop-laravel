<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Import\OneCPriceReader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

#[Signature('products:apply-onec-pairs {file : CSV «Код 1С;id сайта» (первая строка — заголовок)} {--price-list= : Прайс 1С: коды из файла проверяются по нему} {--force : Записать в базу; без флага — только проверка}')]
#[Description('Проставить external_id = код 1С по подтверждённым парам «код 1С — id товара на сайте»')]
class ApplyOneCPairs extends Command
{
    /**
     * Без --force ничего не пишет — только проверяет пары. Запись всё или ничего:
     * при любой ошибке (нет товара, товар удалён, у товара уже другой external_id,
     * код занят или повторяется, кода нет в прайсе) ничего не записывается.
     * Перед запуском: php artisan db:backup.
     */
    public function handle(OneCPriceReader $reader): int
    {
        try {
            $pairs = $this->readPairs($this->argument('file'));
            $priceListCodes = $this->priceListCodes($reader);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $products = Product::query()->whereKey($pairs->pluck('product_id')->all())->get()->keyBy('id');
        $taken = Product::query()->whereIn('external_id', $pairs->pluck('code')->all())->pluck('id', 'external_id');
        $errors = $this->errors($pairs, $products, $taken, $priceListCodes);

        $this->components->twoColumnDetail('Пар в файле', (string) $pairs->count());
        $this->components->twoColumnDetail('Ошибок', (string) $errors->count());

        if ($errors->isNotEmpty()) {
            $this->table(['Код 1С', 'id сайта', 'Ошибка'], $errors->take(30)->all());
            $this->error('Есть ошибки: ничего не записано.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->warn('Пробный прогон: база не изменена. Для записи добавьте --force.');

            return self::SUCCESS;
        }

        DB::transaction(fn () => $pairs->each(fn (array $pair): int => Product::query()->whereKey($pair['product_id'])->update(['external_id' => $pair['code']])));

        $this->info("Проставлен external_id: {$pairs->count()}.");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, array{code: string, product_id: int}>
     */
    private function readPairs(string $path): Collection
    {
        if (! is_file($path)) {
            throw new RuntimeException("Файл {$path} не найден.");
        }

        $contents = (string) preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($path));
        $lines = array_values(array_filter(preg_split('/\R/u', $contents), fn (string $line): bool => trim($line) !== ''));

        if (count($lines) < 2) {
            throw new InvalidArgumentException('В файле нет строк с парами.');
        }

        return collect(array_slice($lines, 1))->map(function (string $line): array {
            $cells = str_getcsv($line, ';', '"', '');

            if (trim($cells[0] ?? '') === '' || ! ctype_digit(trim($cells[1] ?? ''))) {
                throw new InvalidArgumentException("Строка «{$line}» не похожа на пару «код 1С;id сайта».");
            }

            return ['code' => trim($cells[0]), 'product_id' => (int) trim($cells[1])];
        });
    }

    /**
     * @return Collection<string, true>|null
     */
    private function priceListCodes(OneCPriceReader $reader): ?Collection
    {
        if (! $this->option('price-list')) {
            return null;
        }

        return collect($reader->read($this->option('price-list'))['rows'])->mapWithKeys(fn (array $row): array => [$row['external_id'] => true]);
    }

    /**
     * @param  Collection<int, array{code: string, product_id: int}>  $pairs
     * @param  Collection<int, Product>  $products
     * @param  Collection<string, int>  $taken
     * @param  Collection<string, true>|null  $priceListCodes
     * @return Collection<int, array{0: string, 1: int, 2: string}>
     */
    private function errors(Collection $pairs, Collection $products, Collection $taken, ?Collection $priceListCodes): Collection
    {
        $codeCounts = $pairs->countBy('code');
        $productCounts = $pairs->countBy('product_id');

        return $pairs->flatMap(function (array $pair) use ($products, $taken, $priceListCodes, $codeCounts, $productCounts): array {
            $product = $products->get($pair['product_id']);
            $problems = [];

            if ($product === null) {
                $problems[] = 'товара нет или он удалён';
            } elseif ($product->external_id !== null && $product->external_id !== $pair['code']) {
                $problems[] = "у товара уже другой external_id ({$product->external_id})";
            }

            if ($taken->has($pair['code']) && $taken[$pair['code']] !== $pair['product_id']) {
                $problems[] = "код уже у товара {$taken[$pair['code']]}";
            }

            if ($codeCounts[$pair['code']] > 1) {
                $problems[] = 'код повторяется в файле';
            }

            if ($productCounts[$pair['product_id']] > 1) {
                $problems[] = 'товар повторяется в файле';
            }

            if ($priceListCodes !== null && ! $priceListCodes->has($pair['code'])) {
                $problems[] = 'кода нет в прайсе 1С';
            }

            return array_map(fn (string $problem): array => [$pair['code'], $pair['product_id'], $problem], $problems);
        })->values();
    }
}
