<?php

namespace App\Services\CarLanding;

/**
 * Марка, для которой включены посадочные страницы (shop.car_landings.brands).
 */
final readonly class CarBrand
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $fitmentBrand,
        /** @var list<string> */
        public array $skipModels = [],
    ) {}
}
