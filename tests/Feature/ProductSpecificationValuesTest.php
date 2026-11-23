<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSpecificationValuesTest extends TestCase
{
    use RefreshDatabase;

    public function test_several_values_of_one_specification_stay_in_one_block_on_product_page(): void
    {
        $technology = Attribute::query()->create(['name' => 'Технология']);
        $calcium = $technology->values()->create(['value' => 'Ca']);
        $lead = $technology->values()->create(['value' => 'Pb']);

        $product = Product::query()->create(['name' => 'AKOM 65 Ah', 'slug' => 'akom-65-ah', 'price' => 8300, 'status' => true]);
        $product->attributeValues()->attach([$calcium->id, $lead->id]);

        $html = $this->get(route('product.show', $product))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '~product-data__item-div"><span>\s*Ca,\s*Pb\s*</span></div>~u',
            $html,
            'Значения характеристики должны идти в одном блоке, иначе запятая уезжает на отдельную строку сетки.'
        );
    }
}
