<?php

namespace Tests\Unit;

use App\Enums\ServicePriceType;
use PHPUnit\Framework\TestCase;

class ServicePriceTypeTest extends TestCase
{
    public function test_label_depends_on_type_and_amount(): void
    {
        $this->assertSame('Бесплатно', ServicePriceType::Free->label(null));
        $this->assertSame('1 500 ₽', ServicePriceType::Fixed->label(1500));
        $this->assertSame('от 500 ₽', ServicePriceType::From->label(500));
        $this->assertSame('Стоимость согласовывается индивидуально', ServicePriceType::Negotiable->label(null));
        $this->assertSame('Условия уточняйте по телефону', ServicePriceType::ByPhone->label(null));
    }

    public function test_only_fixed_and_from_need_an_amount(): void
    {
        $this->assertSame(['fixed', 'from'], ServicePriceType::valuesWithAmount());
    }
}
