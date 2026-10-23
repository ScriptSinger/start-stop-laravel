<?php

namespace App\Services\Supplier;

use Illuminate\Support\Collection;

/**
 * Итог загрузки прайса поставщика. Для пробного прогона числа те же, что
 * при записи: импорт выполняется в транзакции, которая откатывается.
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
        public int $productsOnOrder,
        public int $productsCleared,
    ) {}
}
