<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class AdminAttributeRelationsTest extends TestCase
{
    use RefreshDatabase;

    private Attribute $polarity;

    private Product $titan;

    private Product $zubr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]), 'moonshine');

        $category = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori']);
        $this->polarity = Attribute::query()->create(['name' => 'Полярность', 'sort_order' => 1]);
        $this->polarity->categories()->attach($category);
        $reverse = $this->polarity->values()->create(['value' => 'Обратная', 'sort_order' => 0]);
        $this->polarity->values()->create(['value' => 'Прямая', 'sort_order' => 1]);
        $voltage = Attribute::query()->create(['name' => 'Напряжение', 'sort_order' => 2]);
        $twelve = $voltage->values()->create(['value' => '12V', 'sort_order' => 0]);

        $this->titan = Product::query()->create(['name' => 'TITAN 60Ah О.П.', 'slug' => 'titan', 'price' => 7000]);
        $this->titan->attributeValues()->attach([$reverse->id, $twelve->id]);
        $this->zubr = Product::query()->create(['name' => 'ZUBR 60Ah П.П.', 'slug' => 'zubr', 'price' => 7000]);
        $this->zubr->attributeValues()->attach([$reverse->id]);
    }

    public function test_product_detail_page_lists_its_attributes(): void
    {
        $this->get("/admin/resource/product-resource/product-detail-page/{$this->titan->id}")
            ->assertOk()
            ->assertSee('Характеристики')
            ->assertSee('Полярность:')
            ->assertSee('Обратная')
            ->assertSee('Напряжение:')
            ->assertSee('12V');
    }

    public function test_product_detail_page_shows_dash_without_attributes(): void
    {
        $bare = Product::query()->create(['name' => 'Лампа', 'slug' => 'lampa', 'price' => 1]);

        $this->get("/admin/resource/product-resource/product-detail-page/{$bare->id}")
            ->assertOk()
            ->assertSee('Характеристики')
            ->assertDontSee('Полярность:');
    }

    public function test_product_list_shows_attribute_counts(): void
    {
        $this->productTable(['search' => 'TITAN'])
            ->assertSee('Хар-ки')
            ->assertSee('TITAN 60Ah О.П.');

        $this->assertSame(2, Product::query()->withCount('attributeValues')->findOrFail($this->titan->id)->attribute_values_count);
    }

    public function test_attribute_list_shows_value_and_product_counts(): void
    {
        $response = $this->get('/admin/component/attribute-index-page/attribute-resource?_component_name=index-table-attribute-resource')->assertOk();

        $response->assertSee('Значений')->assertSee('Товаров')->assertSee('Полярность');
    }

    public function test_attribute_detail_page_shows_values_with_product_counts_and_links(): void
    {
        $this->get("/admin/resource/attribute-resource/attribute-detail-page/{$this->polarity->id}")
            ->assertOk()
            ->assertSee('Полярность')
            ->assertSee('Значения и товары')
            ->assertSee('Обратная')
            ->assertSee('Прямая')
            ->assertSee('filter%5Battribute_values%5D%5B0%5D='.$this->polarity->values()->where('value', 'Обратная')->value('id'), false);
    }

    public function test_product_list_filter_by_attribute_value_is_what_the_link_opens(): void
    {
        $reverseId = $this->polarity->values()->where('value', 'Обратная')->value('id');

        $this->productTable(['filter' => ['attribute_values' => [$reverseId]]])
            ->assertSee('TITAN 60Ah О.П.')
            ->assertSee('ZUBR 60Ah П.П.');

        $this->productTable(['filter' => ['attribute_values' => [$this->polarity->values()->where('value', 'Прямая')->value('id')]]])
            ->assertDontSee('TITAN 60Ah О.П.');
    }

    private function productTable(array $query): TestResponse
    {
        return $this->get('/admin/component/product-index-page/product-resource?'.http_build_query([
            '_component_name' => 'index-table-product-resource',
            ...$query,
        ]))->assertOk();
    }
}
