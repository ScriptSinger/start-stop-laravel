<?php

namespace Tests;

use App\Models\Product;
use App\Models\TradeInType;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Скидка за сдачу старого АКБ берётся из типа корпуса товара (поле
     * «Тип корпуса»): создаём тип с этой суммой и назначаем товару.
     */
    protected function giveTradeIn(Product $product, float $amount): Product
    {
        $type = TradeInType::query()->firstOrCreate(['code' => 'T'.(int) $amount], ['name' => 'Тип '.(int) $amount, 'amount' => $amount]);

        $product->update(['trade_in_type_id' => $type->id]);
        $product->unsetRelation('tradeInType');

        return $product;
    }

    /**
     * Тесты не зависят от собранного фронтенда (public/build): @vite
     * отдаёт пустые теги.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
