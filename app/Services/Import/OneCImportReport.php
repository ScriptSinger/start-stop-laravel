<?php

namespace App\Services\Import;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Результат проверки прайса 1С: что найдено на сайте, что изменится и каких
 * строк на сайте нет. Ничего не записывает: запись — OneCPriceImporter::apply().
 */
final readonly class OneCImportReport
{
    /**
     * @param  Collection<string, array{external_id: string, name: string, price: ?float, quantity: ?float}>  $notFound
     * @param  Collection<int, array{product: Product, attributes: array<string, float|int>, price: array{0: float, 1: float}|null, quantity: array{0: int, 1: int}|null, notes: list<string>}>  $changes
     */
    public function __construct(
        public string $format,
        public int $rows,
        public int $duplicates,
        public int $found,
        public int $withoutPrice,
        public Collection $notFound,
        public Collection $changes,
    ) {}

    public function priceChanges(): int
    {
        return $this->changes->whereNotNull('price')->count();
    }

    public function quantityChanges(): int
    {
        return $this->changes->whereNotNull('quantity')->count();
    }
}
