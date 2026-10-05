<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CatalogFilterTest extends TestCase
{
    use RefreshDatabase;

    private Category $batteries;

    private Attribute $polarity;

    private Attribute $capacity;

    private Manufacturer $titan;

    private Manufacturer $varta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->batteries = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori']);

        $this->polarity = $this->attribute('Полярность', ['Обратная', 'Прямая']);
        $this->capacity = $this->attribute('Ёмкость (Ah)', ['55 - 65 Ah', '66 - 77 Ah', '100 - 110 Ah']);
        $this->attribute('Вязкость', ['5W-30'], inCategory: false);
        $this->attribute('Скрытая', ['Секрет'], filterable: false);

        $this->titan = Manufacturer::query()->create(['name' => 'TITAN', 'slug' => 'titan']);
        $this->varta = Manufacturer::query()->create(['name' => 'VARTA', 'slug' => 'varta']);

        $this->product('TITAN 60 О.П.', $this->titan, ['Обратная', '55 - 65 Ah'], ['price' => 6000, 'quantity' => 2]);
        $this->product('TITAN 60 П.П.', $this->titan, ['Прямая', '55 - 65 Ah'], ['price' => 6100]);
        $this->product('TITAN 75 О.П.', $this->titan, ['Обратная', '66 - 77 Ah'], ['price' => 8000]);
        $this->product('VARTA 60 О.П.', $this->varta, ['Обратная', '55 - 65 Ah', 'Секрет'], ['price' => 9000, 'supplier_quantity' => 5]);
        $this->product('Архивный TITAN', $this->titan, ['Обратная', '100 - 110 Ah'], ['status' => false]);
    }

    public function test_without_filter_shows_active_products_and_filter_groups(): void
    {
        $response = $this->category()
            ->assertSee('TITAN 60 О.П.')
            ->assertSee('VARTA 60 О.П.')
            ->assertDontSee('Архивный TITAN');

        // Блок фильтра: на карточках товаров характеристики видны все, а в
        // фильтре — только привязанные к категории и включённые.
        $filter = $this->filterBlock($response);

        $this->assertStringContainsString('Производитель', $filter);
        $this->assertStringContainsString('Полярность', $filter);
        $this->assertStringContainsString('Ёмкость (Ah)', $filter);
        // Характеристика другой категории и выключенная в фильтре — не показываются.
        $this->assertStringNotContainsString('Вязкость', $filter);
        $this->assertStringNotContainsString('Скрытая', $filter);
        // Значение есть только у неактивного товара — в фильтре его нет.
        $this->assertStringNotContainsString('100 - 110 Ah', $filter);
    }

    public function test_values_inside_attribute_are_or_and_attributes_are_and(): void
    {
        $this->category(['attr' => [
            $this->capacity->id => [$this->value('55 - 65 Ah'), $this->value('66 - 77 Ah')],
            $this->polarity->id => [$this->value('Обратная')],
        ]])
            ->assertSee('TITAN 60 О.П.')
            ->assertSee('TITAN 75 О.П.')
            ->assertSee('VARTA 60 О.П.')
            ->assertDontSee('TITAN 60 П.П.');
    }

    public function test_manufacturer_price_and_availability_filters(): void
    {
        $this->category(['manufacturer' => [$this->varta->id]])
            ->assertSee('VARTA 60 О.П.')
            ->assertDontSee('TITAN 60 О.П.');

        $this->category(['price_from' => 6050, 'price_to' => 8500])
            ->assertSee('TITAN 60 П.П.')
            ->assertSee('TITAN 75 О.П.')
            ->assertDontSee('TITAN 60 О.П.')
            ->assertDontSee('VARTA 60 О.П.');

        // В наличии (TITAN 60 О.П.) или под заказ (VARTA, у поставщика 5).
        $this->category(['available' => 1])
            ->assertSee('TITAN 60 О.П.')
            ->assertSee('VARTA 60 О.П.')
            ->assertDontSee('TITAN 75 О.П.');
    }

    public function test_counts_ignore_own_group_but_respect_others(): void
    {
        $response = $this->category(['manufacturer' => [$this->titan->id]]);

        // Полярность при выбранном TITAN: обратных 2, прямых 1.
        $this->assertFacetCount($response, 'Обратная', 2);
        $this->assertFacetCount($response, 'Прямая', 1);
        // Производители считаются без собственного условия: VARTA остаётся доступной.
        $this->assertFacetCount($response, 'VARTA', 1);
    }

    public function test_value_with_no_results_is_disabled(): void
    {
        $html = $this->category(['manufacturer' => [$this->varta->id]])->getContent();

        $this->assertMatchesRegularExpression(
            '/value="'.$this->value('Прямая').'"\s+disabled/',
            $html,
        );
    }

    public function test_default_sort_is_price_ascending_with_specials_and_can_be_changed(): void
    {
        Product::query()->update(['quantity' => 1]);
        Product::query()->where('name', 'VARTA 60 О.П.')->update(['special_price' => 5000]);

        // По умолчанию — цена по возрастанию (акция учитывается): VARTA 5000, TITAN 6000…
        $this->category()->assertSeeInOrder(['VARTA 60 О.П.', 'TITAN 60 О.П.', 'TITAN 60 П.П.', 'TITAN 75 О.П.']);

        $this->category(['sort' => 'price-desc'])->assertSeeInOrder(['TITAN 75 О.П.', 'TITAN 60 П.П.', 'TITAN 60 О.П.', 'VARTA 60 О.П.']);
        $this->category(['sort' => 'name'])->assertSeeInOrder(['TITAN 60 О.П.', 'TITAN 60 П.П.', 'TITAN 75 О.П.', 'VARTA 60 О.П.']);
        $this->category(['sort' => 'мусор'])->assertSeeInOrder(['VARTA 60 О.П.', 'TITAN 60 О.П.']);
    }

    public function test_out_of_stock_products_go_last_in_any_sort(): void
    {
        Product::query()->update(['quantity' => 1]);
        Product::query()->where('name', 'VARTA 60 О.П.')->update(['quantity' => 0, 'special_price' => 1000]);

        $this->category()->assertSeeInOrder(['TITAN 60 О.П.', 'TITAN 75 О.П.', 'VARTA 60 О.П.']);
        $this->category(['sort' => 'name-desc'])->assertSeeInOrder(['TITAN 75 О.П.', 'TITAN 60 О.П.', 'VARTA 60 О.П.']);
    }

    public function test_per_page_and_pagination_text(): void
    {
        $this->category()->assertSee('Показано с 1 по 4 из 4 (всего 1 страница)');
        $this->category(['limit' => 25])->assertSee('Показано с 1 по 4 из 4');
        // Произвольное число на странице не принимаем.
        $this->category(['limit' => 100000])->assertSee('<option value="'.e(route('category.show', ['category' => $this->batteries, 'limit' => 24])).'" selected>', false);
    }

    public function test_heading_and_meta_description(): void
    {
        $this->batteries->update(['heading' => 'Автомобильные аккумуляторы', 'meta_title' => 'Аккумуляторы в Уфе', 'meta_description' => 'Широкий ассортимент АКБ']);

        $this->category()
            ->assertSee('<h1>Автомобильные аккумуляторы</h1>', false)
            ->assertSee('<title>Аккумуляторы в Уфе</title>', false)
            ->assertSee('<meta name="description" content="Широкий ассортимент АКБ" />', false);
    }

    public function test_garbage_parameters_are_ignored(): void
    {
        $this->category(['attr' => 'abc', 'manufacturer' => ['x', -1], 'price_from' => 'дёшево'])
            ->assertSee('TITAN 60 О.П.')
            ->assertSee('VARTA 60 О.П.');
    }

    public function test_empty_result_offers_reset(): void
    {
        $this->category(['price_from' => 100000])
            ->assertSee('По выбранным условиям ничего не нашлось')
            ->assertSee('Сбросить фильтр');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function category(array $query = []): TestResponse
    {
        return $this->get(route('category.show', ['category' => $this->batteries, ...$query]))->assertOk();
    }

    private function filterBlock(TestResponse $response): string
    {
        $html = $response->getContent();
        $start = strpos($html, 'id="catalog-filter"');
        $this->assertNotFalse($start, 'На странице нет блока фильтра');

        return substr($html, $start, strpos($html, '</form>', $start) - $start);
    }

    private function assertFacetCount(TestResponse $response, string $label, int $expected): void
    {
        $text = preg_replace('/\s+/', ' ', strip_tags($response->getContent()));

        $this->assertStringContainsString("{$label} ({$expected})", $text);
    }

    /**
     * @param  list<string>  $values
     */
    private function attribute(string $name, array $values, bool $inCategory = true, bool $filterable = true): Attribute
    {
        $attribute = Attribute::query()->create(['name' => $name, 'is_filterable' => $filterable]);
        $attribute->values()->createMany(array_map(fn (string $value) => ['value' => $value], $values));

        if ($inCategory) {
            $attribute->categories()->attach($this->batteries);
        }

        return $attribute;
    }

    /**
     * @param  list<string>  $values
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $name, Manufacturer $manufacturer, array $values, array $attributes = []): void
    {
        $product = Product::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'manufacturer_id' => $manufacturer->id,
            'price' => 7000,
            'status' => true,
            ...$attributes,
        ]);

        $product->categories()->attach($this->batteries);
        $product->attributeValues()->attach(AttributeValue::query()->whereIn('value', $values)->pluck('id'));
    }

    private function value(string $value): int
    {
        return AttributeValue::query()->where('value', $value)->value('id');
    }
}
