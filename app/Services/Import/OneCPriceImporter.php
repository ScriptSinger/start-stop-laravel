<?php

namespace App\Services\Import;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Обновление цены и остатка товаров по прайсу из 1С. Правила:
 *  - товар ищется по external_id (код 1С), название и прочие поля не трогаются;
 *  - пустая или нулевая цена — цена на сайте не меняется;
 *  - пустой остаток считается нулём;
 *  - товары сайта, которых нет в файле, не меняются;
 *  - строки файла без товара на сайте не создают товар, а попадают в отчёт.
 */
class OneCPriceImporter
{
    public function __construct(private readonly OneCPriceReader $reader) {}

    /**
     * Прочитать файл и посчитать изменения, ничего не записывая.
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function preview(string $path): OneCImportReport
    {
        $file = $this->reader->read($path);

        $rows = collect($file['rows']);
        $uniqueRows = $rows->keyBy('external_id');
        $products = $this->productsByExternalId($uniqueRows->keys()->all());

        return new OneCImportReport(
            format: $file['format'],
            rows: $rows->count(),
            duplicates: $rows->count() - $uniqueRows->count(),
            found: $products->count(),
            withoutPrice: $uniqueRows->filter(fn (array $row, string $externalId): bool => $products->has($externalId) && ($row['price'] === null || $row['price'] <= 0))->count(),
            notFound: $uniqueRows->reject(fn (array $row, string $externalId): bool => $products->has($externalId)),
            changes: $this->changes($uniqueRows, $products),
        );
    }

    /**
     * Записать изменения из отчёта одной транзакцией.
     */
    public function apply(OneCImportReport $report): int
    {
        DB::transaction(function () use ($report): void {
            foreach ($report->changes as $change) {
                $change['product']->update($change['attributes']);
            }
        });

        return $report->changes->count();
    }

    /**
     * @param  list<string>  $externalIds
     * @return Collection<string, Product>
     */
    private function productsByExternalId(array $externalIds): Collection
    {
        return collect(array_chunk($externalIds, 500))
            ->flatMap(fn (array $chunk) => Product::query()->whereIn('external_id', $chunk)->get())
            ->keyBy('external_id');
    }

    /**
     * @param  Collection<string, array{external_id: string, name: string, price: ?float, quantity: ?float}>  $rows
     * @param  Collection<string, Product>  $products
     * @return Collection<int, array{product: Product, attributes: array<string, float|int>, price: array{0: float, 1: float}|null, quantity: array{0: int, 1: int}|null, notes: list<string>}>
     */
    private function changes(Collection $rows, Collection $products): Collection
    {
        return $rows
            ->filter(fn (array $row, string $externalId): bool => $products->has($externalId))
            ->map(function (array $row, string $externalId) use ($products): array {
                $product = $products[$externalId];
                $attributes = [];
                $price = null;
                $quantity = null;
                $notes = [];

                if ($row['price'] !== null && $row['price'] > 0 && abs((float) $product->price - $row['price']) >= 0.01) {
                    $attributes['price'] = $row['price'];
                    $price = [(float) $product->price, $row['price']];

                    if ($row['price'] < 10) {
                        $notes[] = 'цена в 1С меньше 10 ₽';
                    } elseif ($product->price > 0 && abs((float) $product->price - $row['price']) / (float) $product->price > 0.3) {
                        $notes[] = 'цена меняется больше чем на 30%';
                    }
                }

                $newQuantity = max(0, (int) round($row['quantity'] ?? 0));

                if ((int) $product->quantity !== $newQuantity) {
                    $attributes['quantity'] = $newQuantity;
                    $quantity = [(int) $product->quantity, $newQuantity];

                    if ($row['quantity'] === null && $product->quantity > 0) {
                        $notes[] = 'остаток не указан в 1С — будет 0';
                    }
                }

                return ['product' => $product, 'attributes' => $attributes, 'price' => $price, 'quantity' => $quantity, 'notes' => $notes];
            })
            ->filter(fn (array $change): bool => $change['attributes'] !== [])
            ->values();
    }
}
