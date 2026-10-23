<?php

namespace Tests\Unit;

use App\Services\Supplier\SupplierDelivery;
use App\Services\Supplier\SupplierPricing;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SupplierPricingTest extends TestCase
{
    public function test_retail_price_adds_markup_and_rounds_up_to_fifty(): void
    {
        $this->assertSame(7000.0, SupplierPricing::retailPrice(5800, 20)); // 6960
        $this->assertSame(7050.0, SupplierPricing::retailPrice(5875, 20)); // 7050 ровно
        $this->assertSame(10200.0, SupplierPricing::retailPrice(8460, 20)); // 10152
        $this->assertSame(5000.0, SupplierPricing::retailPrice(5000, 0));
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function deliveries(): array
    {
        return [
            'понедельник + 2' => ['2026-10-19', 2, '2026-10-21'],
            'четверг + 2 через выходные' => ['2026-10-22', 2, '2026-10-26'],
            'пятница + 2' => ['2026-10-23', 2, '2026-10-27'],
            'суббота + 2 как пятница' => ['2026-10-24', 2, '2026-10-27'],
            'воскресенье + 2 как пятница' => ['2026-10-25', 2, '2026-10-27'],
            'пятница + 5' => ['2026-10-23', 5, '2026-10-30'],
            'в тот же день' => ['2026-10-20', 0, '2026-10-20'],
        ];
    }

    #[DataProvider('deliveries')]
    public function test_expected_date_skips_weekends(string $orderedAt, int $days, string $expected): void
    {
        $this->assertSame($expected, SupplierDelivery::expectedDate(CarbonImmutable::parse($orderedAt), $days)->toDateString());
    }

    public function test_format_is_russian(): void
    {
        $this->assertSame('27 октября', SupplierDelivery::format(CarbonImmutable::parse('2026-10-27')));
    }
}
