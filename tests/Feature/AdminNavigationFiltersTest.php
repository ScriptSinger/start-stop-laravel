<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminNavigationFiltersTest extends TestCase
{
    use RefreshDatabase;

    private Attribute $capacity;

    private Attribute $polarity;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($admin, 'moonshine');

        $this->capacity = Attribute::query()->create(['name' => 'Ёмкость (Ah)', 'sort_order' => 1]);
        $this->capacity->values()->createMany([['value' => '55 - 65 Ah'], ['value' => '100 - 110 Ah']]);

        $this->polarity = Attribute::query()->create(['name' => 'Полярность', 'sort_order' => 2]);
        $this->polarity->values()->createMany([['value' => 'Обратная'], ['value' => 'Прямая']]);

        $titan = Manufacturer::query()->create(['name' => 'TITAN', 'slug' => 'titan']);
        $batteries = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori']);

        $this->makeProduct('TITAN 60Ah О.П.', ['code' => '56068', 'manufacturer_id' => $titan->id, 'quantity' => 3], ['55 - 65 Ah', 'Обратная'], $batteries);
        $this->makeProduct('TITAN 60Ah П.П.', ['manufacturer_id' => $titan->id], ['55 - 65 Ah', 'Прямая'], $batteries);
        $this->makeProduct('DELKOR 100Ah О.П.', ['code' => '115D31L'], ['100 - 110 Ah', 'Обратная'], $batteries);
        $this->makeProduct('Архивный аккумулятор', ['status' => false], []);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function indexPages(): array
    {
        return [
            'товары' => ['/admin/resource/product-resource/product-index-page'],
            'категории' => ['/admin/resource/category-resource/category-index-page'],
            'производители' => ['/admin/resource/manufacturer-resource/manufacturer-index-page'],
            'подбор АКБ' => ['/admin/resource/battery-fitment-resource/battery-fitment-index-page'],
            'характеристики' => ['/admin/resource/attribute-resource/attribute-index-page'],
        ];
    }

    #[DataProvider('indexPages')]
    public function test_index_page_with_filters_renders(string $url): void
    {
        $this->get($url)->assertOk();
    }

    public function test_price_filter_is_not_prefilled(): void
    {
        $html = $this->get('/admin/resource/product-resource/product-index-page')->assertOk()->getContent();

        // Без nullable() Range подставлял «от 0 до 1», и любой поиск по
        // фильтрам отсекал все товары дороже 1 ₽.
        $this->assertMatchesRegularExpression("/\\['range_from_filter_price'\\]:\\s*'',\\s*\\['range_to_filter_price'\\]:\\s*'',/", $html);
    }

    public function test_filter_by_category_with_empty_price_range(): void
    {
        $category = Category::query()->where('slug', 'akkumulyatori')->firstOrFail();

        $this->productTable(['filter' => [
            'categories' => [$category->id],
            'price' => ['from' => '', 'to' => ''],
        ]])
            ->assertSee('TITAN 60Ah О.П.')
            ->assertSee('DELKOR 100Ah О.П.')
            ->assertDontSee('Архивный аккумулятор');
    }

    public function test_product_list_shows_linked_categories(): void
    {
        $category = Category::query()->where('slug', 'akkumulyatori')->firstOrFail();

        $this->productTable(['search' => '115D31L'])
            ->assertSee('Аккумуляторы')
            ->assertSee("/admin/resource/category-resource/category-form-page/{$category->id}", false);
    }

    public function test_search_finds_product_by_code(): void
    {
        $this->productTable(['search' => '115D31L'])
            ->assertSee('DELKOR 100Ah О.П.')
            ->assertDontSee('TITAN 60Ah');
    }

    public function test_filter_by_manufacturer(): void
    {
        $titan = Manufacturer::query()->where('slug', 'titan')->firstOrFail();

        $this->productTable(['filter' => ['manufacturer_id' => $titan->id]])
            ->assertSee('TITAN 60Ah О.П.')
            ->assertSee('TITAN 60Ah П.П.')
            ->assertDontSee('DELKOR');
    }

    public function test_attribute_filter_combines_values_with_or_inside_attribute_and_with_and_across_attributes(): void
    {
        $this->productTable(['filter' => ['attribute_values' => [
            $this->value('55 - 65 Ah'),
            $this->value('100 - 110 Ah'),
            $this->value('Обратная'),
        ]]])
            ->assertSee('TITAN 60Ah О.П.')
            ->assertSee('DELKOR 100Ah О.П.')
            ->assertDontSee('TITAN 60Ah П.П.');
    }

    public function test_status_and_stock_filters(): void
    {
        $this->productTable(['filter' => ['status' => '0']])
            ->assertSee('Архивный аккумулятор')
            ->assertDontSee('TITAN 60Ah');

        $this->productTable(['filter' => ['stock' => 'in']])
            ->assertSee('TITAN 60Ah О.П.')
            ->assertDontSee('TITAN 60Ah П.П.')
            ->assertDontSee('DELKOR');
    }

    public function test_missing_data_checkbox_filters_only_when_checked(): void
    {
        $this->productTable(['filter' => ['without_manufacturer' => '1']])
            ->assertSee('DELKOR 100Ah О.П.')
            ->assertDontSee('TITAN 60Ah');

        // Неотмеченный чекбокс тоже приходит в запросе — он не должен фильтровать.
        $this->productTable(['filter' => ['without_manufacturer' => '0', 'without_categories' => '0', 'without_image' => '0']])
            ->assertSee('TITAN 60Ah О.П.')
            ->assertSee('DELKOR 100Ah О.П.');

        $this->productTable(['filter' => ['without_categories' => '1']])
            ->assertSee('Архивный аккумулятор')
            ->assertDontSee('DELKOR');
    }

    public function test_product_list_has_no_query_tag_buttons(): void
    {
        $this->get('/admin/resource/product-resource/product-index-page')
            ->assertOk()
            ->assertDontSee('query-tag=', false);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function productTable(array $query): TestResponse
    {
        return $this->get('/admin/component/product-index-page/product-resource?'.http_build_query([
            '_component_name' => 'index-table-product-resource',
            ...$query,
        ]))->assertOk();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $values
     */
    private function makeProduct(string $name, array $attributes, array $values, ?Category $category = null): void
    {
        $product = Product::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'price' => 9000,
            'status' => true,
            ...$attributes,
        ]);

        $product->attributeValues()->attach(array_map(fn (string $value): int => $this->value($value), $values));

        if ($category) {
            $product->categories()->attach($category);
        }
    }

    private function value(string $value): int
    {
        return AttributeValue::query()->where('value', $value)->value('id');
    }
}
