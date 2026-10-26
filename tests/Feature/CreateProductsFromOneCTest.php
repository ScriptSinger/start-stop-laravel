<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\Import\OneCNewProducts;
use App\Services\Import\OneCProductSpecs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreateProductsFromOneCTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = "\xEF\xBB\xBFКод;Артикул;Наименование;Свободный остаток;Розничная цена\r\n";

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        array_map('unlink', $this->files);

        parent::tearDown();
    }

    /**
     * @return array<string, array{string, string, ?string, string, array<string, string>}>
     */
    public static function batteryNames(): array
    {
        return [
            'обычная' => ['Аккумулятор EDCON 63Ah о.п. 640A L2 242x175x190', 'EDCON 63Ah О.П.', 'L2', 'Аккумуляторы', ['voltage' => '12V', 'capacity' => '63 Ah', 'polarity' => 'Обратная', 'cca' => '640A', 'dimensions' => '242x175x190']],
            'Asia с типоразмером' => ['Аккумулятор ROCKET 60Ah о.п. 520A АЗИЯ 65D23L 232x173x225', 'ROCKET 60Ah О.П. 65D23L', '65D23L', 'Аккумуляторы', ['voltage' => '12V', 'capacity' => '60 Ah', 'polarity' => 'Обратная', 'cca' => '520A', 'dimensions' => '232x173x225']],
            'ASIA в серии' => ['Аккумулятор ZUBR ULTRA ASIA 65Ah о.п.600A D23 230x175x225', 'ZUBR ULTRA ASIA 65Ah О.П.', 'D23', 'Аккумуляторы', ['voltage' => '12V', 'capacity' => '65 Ah', 'polarity' => 'Обратная', 'cca' => '600A', 'dimensions' => '230x175x225']],
            'низкий' => ['Аккумулятор ABSEL 68Ah о.п. 630A LB3L низкий 278x175x175', 'ABSEL 68Ah О.П. низкий', 'LB3L', 'Аккумуляторы', ['voltage' => '12V', 'capacity' => '68 Ah', 'polarity' => 'Обратная', 'cca' => '630A', 'dimensions' => '278x175x175']],
            'прямая полярность, ёмкость в «Ач»' => ['Аккумулятор ДРАЙВ 60 Ач п.п. 540А L2R 242x175x190', 'ДРАЙВ 60Ah П.П.', 'L2R', 'Аккумуляторы', ['voltage' => '12V', 'capacity' => '60 Ah', 'polarity' => 'Прямая', 'cca' => '540A', 'dimensions' => '242x175x190']],
            'грузовой' => ['Аккумулятор VARTA Black Promotive 220 Ah о.п. 1150A ГРУЗ C 513x185x215', 'VARTA Black Promotive 220Ah О.П.', null, 'Грузовые аккумуляторы', ['voltage' => '12V', 'capacity' => '220 Ah', 'polarity' => 'Обратная', 'cca' => '1150A', 'dimensions' => '513x185x215']],
            'мото' => ['Аккумулятор XTREME moto 7 Ah о.п. UTZ7S(YTZ7S) iGEL 114х70х106', 'XTREME moto 7Ah О.П. UTZ7S iGEL', 'UTZ7S', 'Мото аккумуляторы', ['voltage' => '12V', 'capacity' => '7 Ah', 'polarity' => 'Обратная', 'dimensions' => '114x70x106']],
            'мото: «Мото» и бренд раздельно' => ['Аккумулятор Мото BUSHIDO HCZ20H-i 22Ah п.п. 310A YT20-4 177x88x155', 'BUSHIDO moto 22Ah П.П. HCZ20H-i', 'YT20-4', 'Мото аккумуляторы', ['voltage' => '12V', 'capacity' => '22 Ah', 'polarity' => 'Прямая', 'cca' => '310A', 'dimensions' => '177x88x155']],
            'мото без слова «Мото»' => ['Аккумулятор VOLAT 21Ah о.п. 250A YTX21L-BS 205x90x159', 'VOLAT 21Ah О.П. YTX21L-BS', 'YTX21L-BS', 'Мото аккумуляторы', ['voltage' => '12V', 'capacity' => '21 Ah', 'polarity' => 'Обратная', 'cca' => '250A', 'dimensions' => '205x90x159']],
            'Asia без типоразмера — слово Asia' => ['Аккумулятор MUTLU 55Ah о.п. 450A АЗИЯ 238x129x227', 'MUTLU 55Ah Asia О.П.', null, 'Аккумуляторы', ['voltage' => '12V', 'capacity' => '55 Ah', 'polarity' => 'Обратная', 'cca' => '450A', 'dimensions' => '238x129x227']],
            'Asia с длинным типоразмером' => ['Аккумулятор FORGO 36Ah п.п. 480A АЗИЯ 42B19RMF 195x127x200', 'FORGO 36Ah П.П. 42B19RMF', '42B19RMF', 'Аккумуляторы', ['voltage' => '12V', 'capacity' => '36 Ah', 'polarity' => 'Прямая', 'cca' => '480A', 'dimensions' => '195x127x200']],
            'две ёмкости' => ['Аккумулятор DELKOR (JP) 68(70)Ah о.п. 600A АЗИЯ 80D23L 229x172x203', 'DELKOR JP 68Ah О.П. 80D23L', '80D23L', 'Аккумуляторы', ['voltage' => '12V', 'capacity' => '68 Ah', 'polarity' => 'Обратная', 'cca' => '600A', 'dimensions' => '229x172x203']],
        ];
    }

    /**
     * @param  array<string, string>  $attributes
     */
    #[DataProvider('batteryNames')]
    public function test_battery_card_is_built_in_site_style(string $source, string $name, ?string $code, string $category, array $attributes): void
    {
        $spec = (new OneCProductSpecs)->build(['external_id' => '1', 'name' => $source, 'price' => 7000.0, 'quantity' => 2.0]);

        $this->assertSame($name, $spec['name']);
        $this->assertSame($code, $spec['code']);
        $this->assertSame($category, $spec['category']);
        $this->assertSame($attributes, $spec['attributes']);
        $this->assertTrue($spec['is_battery']);
    }

    public function test_name_without_brand_gives_no_manufacturer(): void
    {
        $specs = new OneCProductSpecs;

        $noBrand = $specs->build(['external_id' => '1', 'name' => 'Аккумулятор 65Ah о.п. 580A АЗИЯ 230x172x221', 'price' => null, 'quantity' => null]);
        $this->assertSame('65Ah Asia О.П.', $noBrand['name']);
        $this->assertNull($noBrand['brand']);
        $this->assertContains('в названии 1С нет бренда', $noBrand['warnings']);

        $soviet = $specs->build(['external_id' => '2', 'name' => 'Аккумулятор 3 МТ-8 8Ah о.п. 83x77x141', 'price' => null, 'quantity' => null]);
        $this->assertSame('3 МТ-8 8Ah О.П.', $soviet['name']);
        $this->assertNull($soviet['brand']);
    }

    public function test_same_model_with_different_terminals_gets_distinct_names(): void
    {
        $plan = app(OneCNewProducts::class)->plan(collect([
            ['external_id' => 'А-1', 'name' => 'Аккумулятор FireBall 190Ah п.п. 1200A ruB.4 болт 524x240x243', 'article' => '', 'price' => null, 'quantity' => null],
            ['external_id' => 'А-2', 'name' => 'Аккумулятор FireBall 190Ah п.п. 1200A ruB.4 конус 524x240x242', 'article' => '', 'price' => null, 'quantity' => null],
        ]));

        $this->assertSame(['FireBall 190Ah П.П.', 'FireBall 190Ah П.П. конус'], $plan->pluck('name')->all());
    }

    public function test_moto_brand_and_unbranded_lines_do_not_create_odd_manufacturers(): void
    {
        $plan = app(OneCNewProducts::class)->plan(collect([
            ['external_id' => 'А-1', 'name' => 'Аккумулятор Мото BUSHIDO HCZ20H-i 22Ah п.п. 310A YT20-4 177x88x155', 'article' => '', 'price' => null, 'quantity' => null],
            ['external_id' => 'А-2', 'name' => 'Аккумулятор Premium 61Ah о.п. 540A 242x175x175', 'article' => '', 'price' => null, 'quantity' => null],
            ['external_id' => 'А-3', 'name' => 'Аккумулятор Тюменский медведь 145Ah п.п. 1150A Груз A конус 513x189x236', 'article' => '', 'price' => null, 'quantity' => null],
        ]));

        $this->assertSame('BUSHIDO', $plan[0]['manufacturer']['name']);
        $this->assertNull($plan[1]['manufacturer']);
    }

    public function test_other_goods_keep_the_name_and_get_category_by_keywords(): void
    {
        $specs = new OneCProductSpecs;

        $brush = $specs->build(['external_id' => '1472', 'name' => 'Щетка стеклоочистителя   ALCA WINTER 16 зимняя (400мм)', 'price' => 950.0, 'quantity' => null], '066 000');
        $this->assertSame('Щетка стеклоочистителя ALCA WINTER 16 зимняя (400мм)', $brush['name']);
        $this->assertSame('Стеклоочистители', $brush['category']);
        $this->assertSame('066 000', $brush['sku']);
        $this->assertSame(0, $brush['quantity']);

        $this->assertSame('Очистители', $specs->build(['external_id' => '2', 'name' => 'Очиститель кондиционера 200мл', 'price' => null, 'quantity' => null])['category']);
        $this->assertNull($specs->build(['external_id' => '3', 'name' => 'Нечто непонятное для автомобиля', 'price' => 9000.0, 'quantity' => null])['category']);
        $this->assertContains('нет цены в 1С', $specs->build(['external_id' => '3', 'name' => 'Автокресло', 'price' => null, 'quantity' => null])['warnings']);
    }

    public function test_dry_run_creates_nothing(): void
    {
        $this->artisan('products:create-from-onec', $this->arguments(['1466']))
            ->expectsOutputToContain('Пробный прогон')
            ->assertSuccessful();

        $this->assertSame(0, Product::query()->count());
    }

    public function test_force_creates_hidden_products_with_category_manufacturer_and_attributes(): void
    {
        $this->seedCatalog();

        $this->artisan('products:create-from-onec', [...$this->arguments(['1466', '1472', 'РТ-1']), '--force' => true])
            ->expectsOutputToContain('Создано скрытых товаров: 3')
            ->assertSuccessful();

        $battery = Product::query()->where('external_id', '1466')->firstOrFail();
        $this->assertSame('EDCON 63Ah О.П.', $battery->name);
        $this->assertFalse($battery->status);
        $this->assertNull($battery->sku);
        $this->assertSame('L2', $battery->code);
        $this->assertSame(7000.0, (float) $battery->price);
        $this->assertSame(2, $battery->quantity);
        $this->assertSame(1000.0, (float) $battery->trade_in_discount);
        $this->assertSame('EDCON', $battery->manufacturer->name);
        $this->assertSame(['Аккумуляторы'], $battery->categories->pluck('name')->all());

        $values = $battery->attributeValues->pluck('value')->all();
        $this->assertEqualsCanonicalizing(['12V', 'Обратная', '63 Ah', '55 - 65 Ah', '640А', 'Евро L2 (242 x 175 x 190 мм)'], $values);

        $brush = Product::query()->where('external_id', '1472')->firstOrFail();
        $this->assertSame('shchetka-stekloochistitelya-alca-winter-16-zimnyaya-400mm', $brush->slug);
        $this->assertNull($brush->trade_in_discount);
        $this->assertSame('066 000', $brush->sku);
        $this->assertSame('ALCA', $brush->manufacturer->name);
        $this->assertSame(['Стеклоочистители'], $brush->categories->pluck('name')->all());

        $this->assertNull(Product::query()->where('external_id', 'РТ-1')->firstOrFail()->categories->first());
    }

    public function test_existing_attribute_values_are_reused(): void
    {
        $this->seedCatalog();
        $dims = AttributeValue::query()->where('value', 'Евро L2 (242 x 175 x 190 мм)')->firstOrFail();
        $cca = AttributeValue::query()->where('value', '640А')->firstOrFail();

        $this->artisan('products:create-from-onec', [...$this->arguments(['1466']), '--force' => true])->assertSuccessful();

        $values = Product::query()->where('external_id', '1466')->firstOrFail()->attributeValues->pluck('id');
        $this->assertTrue($values->contains($dims->id));
        $this->assertTrue($values->contains($cca->id));
        $this->assertSame(1, AttributeValue::query()->where('attribute_id', $dims->attribute_id)->count());
    }

    public function test_new_battery_manufacturer_is_created_once_and_existing_one_reused(): void
    {
        $this->seedCatalog();
        $codes = ['1466', 'А-1', 'А-2'];
        $path = $this->file(self::HEADER
            .'1466;;"Аккумулятор EDCON 63Ah о.п. 640A L2 242x175x190";2.000;7000.00'."\r\n"
            .'А-1;;"Аккумулятор ABSEL 60Ah о.п. 610A L2 242x175x190";;'."\r\n"
            .'А-2;;"Аккумулятор ABSEL 62Ah п.п. 610A L2R 242x175x190";;'."\r\n");

        $this->artisan('products:create-from-onec', ['price-list' => $path, 'codes' => $this->codes($codes), '--force' => true])->assertSuccessful();

        $this->assertSame(1, Manufacturer::query()->where('name', 'ABSEL')->count());
        $this->assertSame(1, Manufacturer::query()->where('name', 'EDCON')->count());
        $this->assertSame(Product::query()->where('external_id', 'А-1')->value('manufacturer_id'), Product::query()->where('external_id', 'А-2')->value('manufacturer_id'));
    }

    public function test_brand_spelling_and_aliases_match_existing_manufacturers(): void
    {
        $this->seedCatalog();
        $fireBall = Manufacturer::query()->create(['name' => 'FIRE BALL', 'slug' => 'fire-ball']);
        $tyumen = Manufacturer::query()->create(['name' => 'ТЮМЕНЬ', 'slug' => 'tyumen']);
        $path = $this->file(self::HEADER
            .'А-1;;"Аккумулятор FireBall 60Ah о.п. 510A L2L 242x175x190";;'."\r\n"
            .'А-2;;"Аккумулятор МЕДВЕДЬ 60Ah о.п. 590А L2L 242x175x190";;'."\r\n");

        $this->artisan('products:create-from-onec', ['price-list' => $path, 'codes' => $this->codes(['А-1', 'А-2']), '--force' => true])->assertSuccessful();

        $this->assertSame($fireBall->id, Product::query()->where('external_id', 'А-1')->value('manufacturer_id'));
        $this->assertSame($tyumen->id, Product::query()->where('external_id', 'А-2')->value('manufacturer_id'));
        $this->assertSame(0, Manufacturer::query()->whereIn('name', ['FireBall', 'МЕДВЕДЬ'])->count());
    }

    public function test_codes_that_already_exist_on_site_are_skipped(): void
    {
        $this->seedCatalog();
        Product::query()->create(['name' => 'Уже есть', 'slug' => 'uzhe-est', 'price' => 1, 'external_id' => '1466']);
        $deleted = Product::query()->create(['name' => 'Удалённый', 'slug' => 'udalyonnyj', 'price' => 1, 'external_id' => '1472']);
        $deleted->delete();

        $this->artisan('products:create-from-onec', [...$this->arguments(['1466', '1472']), '--force' => true])
            ->expectsOutputToContain('Создано скрытых товаров: 0')
            ->assertSuccessful();

        $this->assertSame(1, Product::query()->where('external_id', '1466')->count());
        $this->assertSame(0, Product::query()->where('external_id', '1472')->count());
    }

    public function test_variants_with_the_same_name_get_a_distinguishing_suffix(): void
    {
        $this->seedCatalog();
        Product::query()->create(['name' => 'EDCON 63Ah О.П.', 'slug' => 'edcon-63ah-o-p', 'price' => 1]);
        $path = $this->file(self::HEADER
            .'А-1;;"Аккумулятор EDCON 63Ah о.п. 640A L2 242x175x190";;1.00'."\r\n"
            .'А-2;;"Аккумулятор EDCON 63Ah о.п. 600A L2 242x175x190";;1.00'."\r\n"
            .'А-3;;"Аккумулятор EDCON 63Ah о.п. 640A L3 242x175x190";;1.00'."\r\n");

        $this->artisan('products:create-from-onec', ['price-list' => $path, 'codes' => $this->codes(['А-1', 'А-2', 'А-3']), '--force' => true])->assertSuccessful();

        $names = Product::query()->whereIn('external_id', ['А-1', 'А-2', 'А-3'])->orderBy('external_id')->pluck('name')->all();
        $this->assertSame(['EDCON 63Ah О.П. 640A', 'EDCON 63Ah О.П. 600A', 'EDCON 63Ah О.П. L3'], $names);
    }

    public function test_slugs_are_unique(): void
    {
        $this->seedCatalog();
        Product::query()->create(['name' => 'Щетка стеклоочистителя ALCA WINTER 16 зимняя (400мм)', 'slug' => 'shchetka-stekloochistitelya-alca-winter-16-zimnyaya-400mm', 'price' => 1]);

        $this->artisan('products:create-from-onec', [...$this->arguments(['1472']), '--force' => true])->assertSuccessful();

        $this->assertSame('shchetka-stekloochistitelya-alca-winter-16-zimnyaya-400mm-2', Product::query()->where('external_id', '1472')->value('slug'));
    }

    public function test_code_missing_in_price_list_is_reported_and_skipped(): void
    {
        $this->artisan('products:create-from-onec', [...$this->arguments(['1466', '9999']), '--force' => true])
            ->expectsOutputToContain('Нет в прайсе')
            ->assertSuccessful();

        $this->assertSame(1, Product::query()->count());
    }

    public function test_report_file_is_written(): void
    {
        $report = tempnam(sys_get_temp_dir(), 'plan');
        $this->files[] = $report;

        $this->artisan('products:create-from-onec', [...$this->arguments(['1466']), '--report' => $report])->assertSuccessful();

        $csv = file_get_contents($report);
        $this->assertStringContainsString('EDCON 63Ah О.П.', $csv);
        $this->assertStringContainsString('edcon-63ah-o-p', $csv);
    }

    private function seedCatalog(): void
    {
        config(['shop.battery_fitment.capacity_exact_attribute' => 15, 'shop.battery_fitment.attributes.capacity_range' => 20]);

        Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori']);
        Category::query()->create(['name' => 'Стеклоочистители', 'slug' => 'stekloochistiteli']);
        Manufacturer::query()->create(['name' => 'EDCON', 'slug' => 'edcon']);
        Manufacturer::query()->create(['name' => 'ALCA', 'slug' => 'alca']);

        $this->attribute(12, 'Напряжение', ['6V', '12V']);
        $this->attribute(13, 'Полярность', ['Обратная', 'Прямая']);
        $this->attribute(15, 'Емкость', ['60 Ah']);
        $this->attribute(16, 'Габариты', ['Евро L2 (242 x 175 x 190 мм)']);
        $this->attribute(17, 'Ток холодной прокрутки', ['640А']);
        $this->attribute(20, 'Ёмкость (Ah)', ['55 - 65 Ah', '66 - 77 Ah']);
    }

    /**
     * @param  list<string>  $values
     */
    private function attribute(int $id, string $name, array $values): void
    {
        $attribute = new Attribute(['name' => $name, 'sort_order' => 0]);
        $attribute->id = $id;
        $attribute->save();
        $attribute->values()->createMany(array_map(fn (string $value): array => ['value' => $value, 'sort_order' => 0], $values));
    }

    /**
     * @param  list<string>  $codes
     * @return array{price-list: string, codes: string}
     */
    private function arguments(array $codes): array
    {
        return [
            'price-list' => $this->file(self::HEADER
                .'1466;;"Аккумулятор EDCON 63Ah о.п. 640A L2 242x175x190";2.000;7000.00'."\r\n"
                .'1472;066 000;"Щетка стеклоочистителя ALCA WINTER 16 зимняя (400мм)";;950.00'."\r\n"
                .'РТ-1;;"Нечто непонятное для автомобиля";;9000.00'."\r\n"),
            'codes' => $this->codes($codes),
        ];
    }

    /**
     * @param  list<string>  $codes
     */
    private function codes(array $codes): string
    {
        return $this->file("\xEF\xBB\xBFКод 1С\r\n".implode("\r\n", $codes)."\r\n");
    }

    private function file(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'onec');
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }
}
