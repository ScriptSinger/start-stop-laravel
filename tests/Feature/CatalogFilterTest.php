<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\TradeInType;
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

    public function test_short_brand_page_links_to_other_brands(): void
    {
        $this->get(route('category.manufacturer', [$this->batteries, $this->varta]))
            ->assertOk()
            ->assertSee('Смотрите также')
            ->assertSee(route('category.manufacturer', [$this->batteries, $this->titan]), false);
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
        $this->get(route('category.manufacturer', [$this->batteries, $this->varta]))->assertOk()
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

    public function test_toolbar_button_shows_number_of_selected_conditions(): void
    {
        $this->category()
            ->assertSee('catalog-toolbar__filters', false)
            ->assertDontSee('catalog-toolbar__badge', false);

        // Две марки и цена от — три условия; бренд страницы производителя не считается.
        $this->category(['manufacturer' => [$this->titan->id, $this->varta->id], 'price_from' => 6050])
            ->assertSee('<span class="catalog-toolbar__badge" aria-label="выбрано условий: 3">3</span>', false);

        $this->get(route('category.manufacturer', [$this->batteries, $this->varta]))
            ->assertDontSee('catalog-toolbar__badge', false);

        $this->get(route('category.manufacturer', [$this->batteries, $this->varta, 'available' => 1]))
            ->assertSee('aria-label="выбрано условий: 1">1</span>', false);
    }

    public function test_case_type_is_its_own_filter_with_counts_and_or_semantics(): void
    {
        $l2 = TradeInType::query()->create(['code' => 'L2', 'name' => 'L2', 'amount' => 1000, 'sort_order' => 1]);
        $l3 = TradeInType::query()->create(['code' => 'L3', 'name' => 'L3', 'amount' => 1200, 'sort_order' => 2]);
        TradeInType::query()->create(['code' => 'D31', 'name' => 'D31', 'amount' => 1500, 'sort_order' => 3]);
        TradeInType::query()->create(['code' => 'LB2', 'name' => 'LB2', 'amount' => null, 'is_active' => false, 'sort_order' => 4]);
        Product::query()->where('name', 'TITAN 60 О.П.')->update(['trade_in_type_id' => $l2->id]);
        Product::query()->where('name', 'TITAN 60 П.П.')->update(['trade_in_type_id' => $l2->id]);
        Product::query()->where('name', 'TITAN 75 О.П.')->update(['trade_in_type_id' => $l3->id]);

        // Блок «Тип корпуса»: только типы, у которых есть товары в категории, и только активные; код типа, не название.
        $block = $this->filterBlock($this->category());
        $this->assertStringContainsString('Тип корпуса', $block);
        $this->assertStringContainsString('name="case_type[]"', $block);
        $this->assertStringNotContainsString('D31', $block, 'Нет товаров в категории');
        $this->assertStringNotContainsString('LB2', $block, 'Тип выключен');

        $this->assertFacetCount($this->category(), 'L2', 2);
        $this->assertFacetCount($this->category(), 'L3', 1);

        $this->category(['case_type' => [$l2->id]])
            ->assertSee('TITAN 60 О.П.')
            ->assertSee('TITAN 60 П.П.')
            ->assertDontSee('TITAN 75 О.П.')
            ->assertDontSee('VARTA 60 О.П.');

        // Внутри условия значения через «ИЛИ».
        $this->category(['case_type' => [$l2->id, $l3->id]])
            ->assertSee('TITAN 75 О.П.')
            ->assertSee('TITAN 60 О.П.')
            ->assertDontSee('VARTA 60 О.П.');

        // С другими условиями — «И»; счётчик типа учитывает остальные выбранные условия.
        $this->category(['case_type' => [$l2->id], 'attr' => [$this->polarity->id => [$this->value('Обратная')]]])
            ->assertSee('TITAN 60 О.П.')
            ->assertDontSee('TITAN 60 П.П.');
        $this->assertFacetCount($this->category(['attr' => [$this->polarity->id => [$this->value('Прямая')]]]), 'L2', 1);
        $this->assertFacetCount($this->category(['attr' => [$this->polarity->id => [$this->value('Прямая')]]]), 'L3', 0);

        $this->getJson(route('category.count', ['category' => $this->batteries, 'case_type' => [$l2->id]]))->assertExactJson(['count' => 2]);
    }

    public function test_long_filter_group_lists_available_values_first_and_dimensions_by_size(): void
    {
        config(['shop.battery_fitment.attributes.dimensions' => 0]);
        $sizes = $this->attribute('Габариты', ['400 x 100 x 100 мм', '65-750 (302x182x192)', '100 x 100 x 100 мм', '200 x 100 x 100 мм', '10 x 10 x 10 мм', '11 x 10 x 10 мм', '12 x 10 x 10 мм', '13 x 10 x 10 мм', '14 x 10 x 10 мм', '15 x 10 x 10 мм']);
        config(['shop.battery_fitment.attributes.dimensions' => $sizes->id]);
        // Доступны только два значения; остальные в категории есть у других товаров, но при выбранной полярности (0).
        $titanAndVarta = Product::query()->whereIn('name', ['TITAN 60 О.П.', 'VARTA 60 О.П.'])->get();
        $titanAndVarta[0]->attributeValues()->attach($this->value('400 x 100 x 100 мм'));
        $titanAndVarta[1]->attributeValues()->attach($this->value('200 x 100 x 100 мм'));
        $other = Product::query()->where('name', 'TITAN 60 П.П.')->first();
        foreach (['10 x 10 x 10 мм', '11 x 10 x 10 мм', '12 x 10 x 10 мм', '13 x 10 x 10 мм', '14 x 10 x 10 мм', '15 x 10 x 10 мм', '100 x 100 x 100 мм', '65-750 (302x182x192)'] as $value) {
            $other->attributeValues()->attach($this->value($value));
        }

        $block = $this->filterBlock($this->category(['attr' => [$this->polarity->id => [$this->value('Обратная')]]]));
        $position = fn (string $label): int => strpos($block, "data-label=\"{$label}\"");

        // Доступные (с товарами при выбранной полярности) выше недоступных, внутри — по размеру.
        $this->assertLessThan($position('10 x 10 x 10 мм'), $position('200 x 100 x 100 мм'));
        $this->assertLessThan($position('400 x 100 x 100 мм'), $position('200 x 100 x 100 мм'), 'По размеру: 200 раньше 400');
        $this->assertLessThan($position('10 x 10 x 10 мм'), $position('400 x 100 x 100 мм'));
        // Недоступные тоже по размеру, а «65-750 (…)» в самом конце.
        $this->assertLessThan($position('11 x 10 x 10 мм'), $position('10 x 10 x 10 мм'));
        $this->assertLessThan($position('65-750 (302x182x192)'), $position('100 x 100 x 100 мм'));
    }

    public function test_price_filters_and_bounds_use_displayed_prices(): void
    {
        Product::query()->where('name', 'TITAN 60 О.П.')->update(['price' => 9000, 'special_price' => 4000]);
        Product::query()->where('name', 'VARTA 60 О.П.')->update(['supplier_price' => 12000]);

        $this->category(['price_to' => 4500])->assertViewHas('products', fn ($products) => $products->pluck('name')->all() === ['TITAN 60 О.П.']);
        $this->category(['price_from' => 11000])->assertViewHas('products', fn ($products) => $products->pluck('name')->all() === ['VARTA 60 О.П.']);
        $this->category()->assertViewHas('priceMin', 4000)->assertViewHas('priceMax', 12000);
        $this->getJson(route('category.count', ['category' => $this->batteries, 'price_to' => 4500]))->assertExactJson(['count' => 1]);
    }

    public function test_chosen_sort_ignores_stock_and_default_sort_puts_in_stock_first(): void
    {
        Product::query()->update(['quantity' => 0]);
        Product::query()->where('name', 'VARTA 60 О.П.')->update(['quantity' => 3, 'price' => 12000]);

        // Сортировка по цене — чистая: дорогой товар в наличии не уходит в начало.
        $this->category(['sort' => 'price'])->assertViewHas('products', fn ($products) => $products->pluck('name')->last() === 'VARTA 60 О.П.');
        $this->category(['sort' => 'price-desc'])->assertViewHas('products', fn ($products) => $products->pluck('name')->first() === 'VARTA 60 О.П.');
        $this->category(['sort' => 'name-desc'])->assertViewHas('products', fn ($products) => $products->pluck('name')->first() === 'VARTA 60 О.П.');

        // Сортировку не выбирали — товар в наличии идёт первым, хоть и стоит дороже.
        $this->category(['sort' => 'default'])->assertViewHas('products', fn ($products) => $products->pluck('name')->first() === 'VARTA 60 О.П.');
        $this->category()->assertViewHas('products', fn ($products) => $products->pluck('name')->first() === 'VARTA 60 О.П.');
    }

    public function test_price_sort_uses_valid_special_and_supplier_prices(): void
    {
        Product::query()->update(['quantity' => 0]);
        Product::query()->where('name', 'TITAN 60 О.П.')->update(['special_price' => 7000]);
        Product::query()->where('name', 'TITAN 60 П.П.')->update(['supplier_quantity' => 5, 'supplier_price' => 2000]);
        Product::query()->where('name', 'TITAN 75 О.П.')->update(['supplier_quantity' => 5, 'supplier_price' => 3000, 'special_price' => 4000]);

        $expected = ['TITAN 60 П.П.', 'TITAN 75 О.П.', 'TITAN 60 О.П.', 'VARTA 60 О.П.'];
        $this->category(['sort' => 'price'])->assertViewHas('products', fn ($products) => $products->pluck('name')->all() === $expected);
        $this->category(['sort' => 'price-desc'])->assertViewHas('products', fn ($products) => $products->pluck('name')->all() === array_reverse($expected));
    }

    public function test_counts_ignore_own_group_but_respect_others(): void
    {
        // Один производитель без других условий — страница производителя, куда ведёт 301.
        $response = $this->get(route('category.manufacturer', [$this->batteries, $this->titan]))->assertOk();

        // Полярность при выбранном TITAN: обратных 2, прямых 1.
        $this->assertFacetCount($response, 'Обратная', 2);
        $this->assertFacetCount($response, 'Прямая', 1);
        // Производители считаются без собственного условия: VARTA остаётся доступной.
        $this->assertFacetCount($response, 'VARTA', 1);
    }

    public function test_value_with_no_results_is_disabled(): void
    {
        $html = $this->get(route('category.manufacturer', [$this->batteries, $this->varta]))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/value="'.$this->value('Прямая').'"[^>]*\sdisabled/',
            $html,
        );
    }

    public function test_count_endpoint_for_mobile_filter_button(): void
    {
        $this->getJson(route('category.count', ['category' => $this->batteries]))
            ->assertOk()
            ->assertExactJson(['count' => 4]);

        $this->getJson(route('category.count', ['category' => $this->batteries, 'manufacturer' => [$this->titan->id], 'attr' => [$this->polarity->id => [$this->value('Прямая')]]]))
            ->assertExactJson(['count' => 1]);
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

    public function test_out_of_stock_products_go_last_unless_sort_is_chosen(): void
    {
        Product::query()->update(['quantity' => 1]);
        Product::query()->where('name', 'VARTA 60 О.П.')->update(['quantity' => 0, 'special_price' => 1000]);

        // Сортировку не выбирали (адрес без параметра и «По умолчанию») — в наличии первыми.
        $this->category()->assertSeeInOrder(['TITAN 60 О.П.', 'TITAN 75 О.П.', 'VARTA 60 О.П.']);
        $this->category(['sort' => 'default'])->assertSeeInOrder(['TITAN 60 О.П.', 'TITAN 75 О.П.', 'VARTA 60 О.П.']);
        // Выбранная сортировка чистая: VARTA с акцией 1000 ₽ — самая дешёвая, хоть и нет в наличии.
        $this->category(['sort' => 'price'])->assertSeeInOrder(['VARTA 60 О.П.', 'TITAN 60 О.П.', 'TITAN 75 О.П.']);
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
            ->assertSee('<meta name="description" content="Широкий ассортимент АКБ">', false);
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
