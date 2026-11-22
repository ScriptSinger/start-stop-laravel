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
     * @param  list<int>  $caseTypeIds  id типов корпуса (поле товара «Тип корпуса»)
     */
    public function __construct(
        public array $manufacturerIds = [],
        public array $attributeValueIds = [],
        public ?float $priceFrom = null,
        public ?float $priceTo = null,
        public bool $onlyAvailable = false,
        public array $caseTypeIds = [],
    ) {}

    public function isActive(): bool
    {
        return $this->manufacturerIds !== []
            || $this->attributeValueIds !== []
            || $this->priceFrom !== null
            || $this->priceTo !== null
            || $this->onlyAvailable
            || $this->caseTypeIds !== [];
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
        return new self([], $this->attributeValueIds, $this->priceFrom, $this->priceTo, $this->onlyAvailable, $this->caseTypeIds);
    }

    /**
     * Тот же фильтр с другим набором производителей — для страницы производителя,
     * где бренд задан адресом, а не параметром.
     *
     * @param  list<int>  $manufacturerIds
     */
    public function withManufacturers(array $manufacturerIds): self
    {
        return new self($manufacturerIds, $this->attributeValueIds, $this->priceFrom, $this->priceTo, $this->onlyAvailable, $this->caseTypeIds);
    }

    /**
     * Тот же фильтр без условия по типу корпуса — для счётчиков в блоке «Тип корпуса».
     */
    public function withoutCaseTypes(): self
    {
        return new self($this->manufacturerIds, $this->attributeValueIds, $this->priceFrom, $this->priceTo, $this->onlyAvailable);
    }

    /**
     * Тот же фильтр без условия по одной характеристике — для её счётчиков.
     */
    public function withoutAttribute(int $attributeId): self
    {
        $attributeValueIds = $this->attributeValueIds;
        unset($attributeValueIds[$attributeId]);

        return new self($this->manufacturerIds, $attributeValueIds, $this->priceFrom, $this->priceTo, $this->onlyAvailable, $this->caseTypeIds);
    }
}
