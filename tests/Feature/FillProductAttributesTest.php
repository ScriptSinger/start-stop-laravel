<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FillProductAttributesTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = "\xEF\xBB\xBFКод;Артикул;Наименование;Свободный остаток;Розничная цена\r\n";

    /** @var list<string> */
    private array $files = [];

    private Category $batteries;

    private Category $oils;

    protected function tearDown(): void
    {
        array_map('unlink', $this->files);

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->batteries = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori']);
        $this->oils = Category::query()->create(['name' => 'Моторное масло', 'slug' => 'motornoe-maslo']);
        config(['shop.battery_fitment.category_ids' => [$this->batteries->id]]);

        $battery = ['Напряжение' => ['6V', '12V'], 'Полярность' => ['Обратная', 'Прямая'], 'Емкость' => ['60 Ah'], 'Габариты' => ['Евро L2 (242 x 175 x 190 мм)'],
            'Ток холодной прокрутки' => ['640А'], 'Технология' => ['AGM', 'EFB', 'Gel'], 'Ёмкость (Ah)' => ['55 - 65 Ah', '60 - 70 Ah'], 'Ток холодного пуска (А)' => ['600 - 690 A'], 'Производство' => ['Китай', 'Россия']];
        $oil = ['Вязкость' => ['5W30'], 'Объем' => ['1л.'], 'Состав' => ['Синтетические', 'Полусинтетические'], 'API' => ['SN', 'CF'], 'ACEA' => ['C3']];

        foreach ($battery as $name => $values) {
            $this->attribute($name, $values, $this->batteries);
        }

        foreach ($oil as $name => $values) {
            $this->attribute($name, $values, $this->oils);
        }

        config(['shop.battery_fitment.capacity_exact_attribute' => Attribute::query()->where('name', 'Емкость')->value('id')]);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $product = $this->battery('TITAN 60Ah О.П.');

        $this->artisan('products:fill-attributes')->expectsOutputToContain('Пробный прогон')->assertSuccessful();

        $this->assertSame([], $this->values($product));
    }

    public function test_battery_gets_capacity_ranges_voltage_and_polarity_from_name(): void
    {
        $product = $this->battery('TITAN 60Ah О.П.');

        $this->artisan('products:fill-attributes', ['--force' => true])->assertSuccessful();

        $this->assertEqualsCanonicalizing(['Емкость: 60 Ah', 'Ёмкость (Ah): 55 - 65 Ah', 'Ёмкость (Ah): 60 - 70 Ah', 'Напряжение: 12V', 'Полярность: Обратная'], $this->values($product));
    }

    public function test_existing_values_are_never_changed(): void
    {
        $product = $this->battery('TITAN 60Ah О.П.');
        $product->attributeValues()->attach($this->value('Полярность', 'Прямая'));

        $this->artisan('products:fill-attributes', ['--force' => true])->assertSuccessful();

        $this->assertContains('Полярность: Прямая', $this->values($product));
        $this->assertNotContains('Полярность: Обратная', $this->values($product));
    }

    public function test_current_and_dimensions_come_from_the_onec_name_and_reuse_existing_values(): void
    {
        $product = $this->battery('TITAN 60Ah О.П.', ['external_id' => 'РТ-1']);
        $priceList = $this->priceList('РТ-1;;"Аккумулятор TITAN 60Ah о.п. 640A L2 242x175x190";;7000.00');

        $this->artisan('products:fill-attributes', ['--price-list' => $priceList, '--force' => true])->assertSuccessful();

        $values = $this->values($product);
        $this->assertContains('Ток холодной прокрутки: 640А', $values);
        $this->assertContains('Габариты: Евро L2 (242 x 175 x 190 мм)', $values);
        $this->assertContains('Ток холодного пуска (А): 600 - 690 A', $values);
        $this->assertSame(1, AttributeValue::query()->where('attribute_id', $this->attributeId('Габариты'))->count());
    }

    public function test_new_dictionary_values_are_created_for_open_attributes(): void
    {
        $product = $this->battery('TITAN 75Ah О.П.', ['external_id' => 'РТ-2']);
        $priceList = $this->priceList('РТ-2;;"Аккумулятор TITAN 75Ah о.п. 720A L3 278x175x190";;9000.00');

        $this->artisan('products:fill-attributes', ['--price-list' => $priceList, '--force' => true])->assertSuccessful();

        $values = $this->values($product);
        $this->assertContains('Емкость: 75 Ah', $values);
        $this->assertContains('Ток холодной прокрутки: 720A', $values);
        $this->assertContains('Габариты: 278x175x190', $values);
    }

    public function test_technology_from_name_and_closed_dictionaries_are_not_extended(): void
    {
        $agm = $this->battery('DELKOR AGM 60Ah О.П.');
        $unknown = $this->battery('KAINAR 60Ah О.П. ТРОЙНОЙ');

        $this->artisan('products:fill-attributes', ['--force' => true])->assertSuccessful();

        $this->assertContains('Технология: AGM', $this->values($agm));
        $this->assertStringNotContainsString('Технология', implode('|', $this->values($unknown)));
    }

    public function test_country_comes_from_the_main_country_of_the_manufacturer(): void
    {
        $manufacturer = Manufacturer::query()->create(['name' => 'XTREME', 'slug' => 'xtreme']);
        $china = $this->value('Производство', 'Китай');

        foreach (['A', 'B', 'C'] as $letter) {
            $this->battery("XTREME {$letter} 60Ah О.П.", ['manufacturer_id' => $manufacturer->id])->attributeValues()->attach($china);
        }

        $product = $this->battery('XTREME Silver 60Ah О.П.', ['manufacturer_id' => $manufacturer->id]);

        $this->artisan('products:fill-attributes', ['--force' => true])->assertSuccessful();

        $this->assertContains('Производство: Китай', $this->values($product));
    }

    public function test_oil_gets_viscosity_volume_composition_and_api_from_name(): void
    {
        $oil = Product::query()->create(['name' => 'Моторное Масло Shell Helix 5w30 API SN/CF (синт.) 1л', 'slug' => 'shell', 'price' => 1]);
        $oil->categories()->attach($this->oils);

        $this->artisan('products:fill-attributes', ['--force' => true])->assertSuccessful();

        $this->assertEqualsCanonicalizing(['Вязкость: 5W30', 'Объем: 1л.', 'Состав: Синтетические', 'API: SN', 'API: CF'], $this->values($oil));
    }

    public function test_attributes_of_other_categories_and_products_without_category_are_skipped(): void
    {
        $oil = Product::query()->create(['name' => 'Моторное Масло 5w30 60Ah О.П. 12V', 'slug' => 'oil', 'price' => 1]);
        $oil->categories()->attach($this->oils);
        $loose = Product::query()->create(['name' => 'TITAN 60Ah О.П.', 'slug' => 'loose', 'price' => 1]);

        $this->artisan('products:fill-attributes', ['--force' => true])->assertSuccessful();

        $this->assertStringNotContainsString('Емкость', implode('|', $this->values($oil)));
        $this->assertSame([], $this->values($loose));
    }

    public function test_report_file_is_written(): void
    {
        $this->battery('TITAN 60Ah О.П.');
        $report = tempnam(sys_get_temp_dir(), 'attr');
        $this->files[] = $report;

        $this->artisan('products:fill-attributes', ['--report' => $report])->assertSuccessful();

        $this->assertStringContainsString('Полярность: Обратная', (string) file_get_contents($report));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function battery(string $name, array $attributes = []): Product
    {
        $product = Product::query()->create($attributes + ['name' => $name, 'slug' => str($name)->slug()->value().'-'.Product::query()->count(), 'price' => 7000]);
        $product->categories()->attach($this->batteries);

        return $product;
    }

    /**
     * @param  list<string>  $values
     */
    private function attribute(string $name, array $values, Category $category): void
    {
        $attribute = Attribute::query()->firstOrCreate(['name' => $name], ['sort_order' => 0]);
        $attribute->categories()->syncWithoutDetaching([$category->id]);

        foreach ($values as $value) {
            $attribute->values()->firstOrCreate(['value' => $value], ['sort_order' => 0]);
        }
    }

    private function attributeId(string $name): int
    {
        return (int) Attribute::query()->where('name', $name)->value('id');
    }

    private function value(string $attribute, string $value): int
    {
        return (int) Attribute::query()->findOrFail($this->attributeId($attribute))->values()->where('value', $value)->value('id');
    }

    /**
     * @return list<string>
     */
    private function values(Product $product): array
    {
        return $product->fresh()->attributeValues()->with('attribute')->get()->map(fn ($value): string => "{$value->attribute->name}: {$value->value}")->all();
    }

    private function priceList(string $line): string
    {
        $path = tempnam(sys_get_temp_dir(), 'onec');
        file_put_contents($path, self::HEADER.$line."\r\n");
        $this->files[] = $path;

        return $path;
    }
}
