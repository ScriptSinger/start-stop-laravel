<?php

namespace App\Services\Supplier;

use Illuminate\Support\Collection;

/**
 * Итог загрузки прайса поставщика. Пробный прогон показывает ожидаемые
 * изменения при условии, что данные до применения не изменятся.
 */
final readonly class SupplierImportReport
{
    /**
     * @param  Collection<int, array{code: string, name: string, cost: float}>  $unlinked  позиции без товара на сайте
     * @param  Collection<int, array{name: string, old: float, new: float}>  $costChanges
     */
    public function __construct(
        public int $rows,
        public int $duplicates,
        public int $created,
        public int $costChanged,
        public int $vanished,
        public int $linkedByName,
        public Collection $unlinked,
        public Collection $costChanges,
        public int $productsAdded,
        public int $productsRepriced,
        public int $productsCleared,
    ) {}
}
