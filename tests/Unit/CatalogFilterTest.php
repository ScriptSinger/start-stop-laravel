<?php

namespace Tests\Unit;

use App\Services\Catalog\CatalogFilter;
use PHPUnit\Framework\TestCase;

class CatalogFilterTest extends TestCase
{
    public function test_is_active_only_with_conditions(): void
    {
        $this->assertFalse((new CatalogFilter)->isActive());
        $this->assertTrue((new CatalogFilter(onlyAvailable: true))->isActive());
        $this->assertTrue((new CatalogFilter(priceTo: 5000))->isActive());
    }

    public function test_without_methods_drop_one_condition_and_keep_original_intact(): void
    {
        $filter = new CatalogFilter(
            manufacturerIds: [5],
            attributeValueIds: [13 => [1], 20 => [7, 8]],
            priceFrom: 1000,
        );

        $withoutCapacity = $filter->withoutAttribute(20);
        $this->assertSame([13 => [1]], $withoutCapacity->attributeValueIds);
        $this->assertSame([5], $withoutCapacity->manufacturerIds);
        $this->assertSame(1000.0, $withoutCapacity->priceFrom);

        $withoutManufacturers = $filter->withoutManufacturers();
        $this->assertSame([], $withoutManufacturers->manufacturerIds);
        $this->assertSame([13 => [1], 20 => [7, 8]], $withoutManufacturers->attributeValueIds);

        // Исходный фильтр не меняется.
        $this->assertSame([5], $filter->manufacturerIds);
        $this->assertSame([7, 8], $filter->selectedValueIds(20));
        $this->assertSame([], $filter->selectedValueIds(99));
    }
}
