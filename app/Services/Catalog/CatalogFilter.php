<?php

namespace App\Services\Catalog;

/**
 * Выбранные условия фильтра каталога. Внутри условия значения через «ИЛИ»,
 * между условиями — «И» (см. Product::catalogFilter()).
 */
final readonly class CatalogFilter
{
    /**
     * @param  list<int>  $manufacturerIds
     * @param  array<int, list<int>>  $attributeValueIds  id характеристики → id выбранных значений
     */
    public function __construct(
        public array $manufacturerIds = [],
        public array $attributeValueIds = [],
        public ?float $priceFrom = null,
        public ?float $priceTo = null,
        public bool $onlyAvailable = false,
    ) {}

    public function isActive(): bool
    {
        return $this->manufacturerIds !== []
            || $this->attributeValueIds !== []
            || $this->priceFrom !== null
            || $this->priceTo !== null
            || $this->onlyAvailable;
    }

    /**
     * @return list<int>
     */
    public function selectedValueIds(int $attributeId): array
    {
        return $this->attributeValueIds[$attributeId] ?? [];
    }

    /**
     * Тот же фильтр без условия по производителю — для счётчиков в блоке
     * «Производитель»: «сколько будет, если отметить ещё и этого».
     */
    public function withoutManufacturers(): self
    {
        return new self([], $this->attributeValueIds, $this->priceFrom, $this->priceTo, $this->onlyAvailable);
    }

    /**
     * Тот же фильтр без условия по одной характеристике — для её счётчиков.
     */
    public function withoutAttribute(int $attributeId): self
    {
        $attributeValueIds = $this->attributeValueIds;
        unset($attributeValueIds[$attributeId]);

        return new self($this->manufacturerIds, $attributeValueIds, $this->priceFrom, $this->priceTo, $this->onlyAvailable);
    }
}
