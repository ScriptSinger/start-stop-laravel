<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\BatteryFitment;
use App\Models\Category;
use App\Models\Product;
use App\Services\CarLanding\CarLandingCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarLandingTest extends TestCase
{
    use RefreshDatabase;

    private Category $batteries;

    protected function setUp(): void
    {
        parent::setUp();

        config(['shop.car_landings' => [
            'excluded_brands' => ['Грузовики'],
            'brand_names' => ['ВАЗ (Lada)' => 'Lada'],
            'skip_models' => ['ВАЗ (Lada)' => ['XRAY concept']],
        ]]);

        $this->batteries = Category::query()->forceCreate(['id' => 1, 'name' => 'Аккумуляторы', 'slug' => 'akkumulyatori']);

        // id характеристик — как в OCFilter (см. shop.battery_fitment.attributes).
        $this->attribute(13, 'Полярность', ['Обратная', 'Прямая', 'Универсальная']);
        $this->attribute(20, 'Ёмкость (Ah)', ['55 - 65 Ah', '66 - 77 Ah']);
        $this->attribute(16, 'Габариты', ['Евро L2 (242 x 175 x 190 мм)', 'Евро L3 (278 x 175 x 190 мм)']);

        // Одна модель записана по-разному и в нескольких поколениях.
        $this->fitment('ВАЗ (Lada) Vesta', null, 'Обратная', '60 Ач, 55 Ач');
        $this->fitment('Vesta', 'ВАЗ (Lada) Vesta I 2015 - 2022', 'Обратная', '60 Ач, 55 Ач');
        $this->fitment('Vesta', 'I 2015 - 2022', 'Обратная', '62 Ач', '246x175x190');
        $this->fitment('Vesta', 'ВАЗ (Lada) Vesta I 2015 - 2022 1.8', 'Обратная', '60 Ач, 55 Ач');
        $this->fitment('Vesta', 'ВАЗ (Lada) Vesta I Рестайлинг 2022 - н.в.', 'Обратная', '62 Ач, 65 Ач');
        $this->fitment('2107', 'ВАЗ (Lada) 2107 1982 - 2012', 'Прямая', '60 Ач');
        // Поколения с разными аккумуляторами — у каждого своя страница.
        $this->fitment('2131 (4x4)', 'ВАЗ (Lada) 2131 (4x4) I Рестайлинг 2019 - 2021', 'Обратная', '60 Ач');
        $this->fitment('ВАЗ (Lada) 2131 (4x4)', 'ВАЗ (Lada) 2131 (4x4) I 1993 - 2019', 'Прямая', '60 Ач');
        $this->fitment('ВАЗ (Lada) XRAY concept', null, 'Обратная', '60 Ач');
        // Под эту машину товаров нет — страницы у неё быть не должно.
        $this->fitment('Largus', 'ВАЗ (Lada) Largus I 2012 - 2021', 'Обратная', '75 Ач', '278x175x190');
        BatteryFitment::query()->create(['brand' => 'Kia', 'model' => 'Rio', 'capacity' => '60 Ач', 'polarity' => 'Обратная']);

        $this->battery('TITAN 60Ah О.П.', ['Обратная', '55 - 65 Ah', 'Евро L2 (242 x 175 x 190 мм)'], price: 7000, quantity: 2);
        $this->battery('ZUBR 62Ah О.П.', ['Обратная', '55 - 65 Ah', 'Евро L2 (242 x 175 x 190 мм)'], price: 5500);
        $this->battery('TITAN 60Ah П.П.', ['Прямая', '55 - 65 Ah', 'Евро L2 (242 x 175 x 190 мм)'], price: 6500, quantity: 1);
    }

    public function test_index_lists_all_brands_with_pages(): void
    {
        $this->get(route('car-landing.index'))
            ->assertOk()
            ->assertSee('<h1>Аккумуляторы по марке автомобиля</h1>', false)
            ->assertSeeInOrder(['Kia', 'Lada'])
            ->assertSee(route('car-landing.brand', 'kia'))
            ->assertSee(route('car-landing.brand', 'lada'));

        $this->get(route('car-landing.model', ['lada', 'vesta']))
            ->assertSee('<a href="'.route('car-landing.index').'">По марке авто</a>', false);
    }

    public function test_generation_page_offers_engines_when_they_need_different_batteries(): void
    {
        $this->fitment('2131 (4x4)', 'I 1993 - 2019', 'Прямая', '60 Ач', engine: '1.7 бензин');
        $this->fitment('2131 (4x4)', 'I 1993 - 2019', 'Обратная', '60 Ач', engine: '1.8 бензин');

        $this->get(route('car-landing.generation', ['lada', '2131-4x4', 'i-1993-2019']))
            ->assertOk()
            ->assertSee('Разным двигателям подходят разные аккумуляторы')
            ->assertSee(e(route('battery-selection', ['brand' => 'ВАЗ (Lada)', 'model' => '2131 (4x4)', 'gen' => 'I 1993 - 2019', 'engine' => '1.7 бензин'])), false);

        $this->get(route('car-landing.generation', ['lada', 'vesta', 'i-2015-2022']))
            ->assertOk()
            ->assertDontSee('Разным двигателям подходят разные аккумуляторы');
    }

    public function test_brand_page_lists_only_models_with_products(): void
    {
        $this->get(route('car-landing.brand', 'lada'))
            ->assertOk()
            ->assertSee('<title>Аккумуляторы для Lada — купить в Уфе | Старт-Стоп</title>', false)
            ->assertSee('<h1>Аккумуляторы для Lada</h1>', false)
            ->assertSeeInOrder(['Lada 2107', 'Lada Vesta'])
            ->assertSee(route('car-landing.model', ['lada', 'vesta']))
            ->assertDontSee('Largus')
            ->assertDontSee('XRAY');
    }

    public function test_model_page_merges_spellings_and_shows_fitting_products(): void
    {
        $this->get(route('car-landing.model', ['lada', 'vesta']))
            ->assertOk()
            ->assertSee('<title>Аккумулятор для Lada Vesta — купить в Уфе | Старт-Стоп</title>', false)
            ->assertSee('<meta name="description" content="Аккумуляторы для Lada Vesta в Уфе: 2 подходящих аккумулятора от 5 500 ₽, ёмкость 55–65 Ач.', false)
            ->assertSee('<link rel="canonical" href="'.route('car-landing.model', ['lada', 'vesta']).'">', false)
            ->assertSee('<h1>Аккумулятор для Lada Vesta</h1>', false)
            ->assertSee('TITAN 60Ah О.П.')
            ->assertSee('ZUBR 62Ah О.П.')
            ->assertDontSee('TITAN 60Ah П.П.')
            ->assertSee('с обратной полярностью')
            ->assertSee('длиной корпуса 242–246 мм и высотой до 190 мм')
            ->assertSee('Полярность, «+» справа')
            ->assertSee('2 варианта, 1 в наличии')
            ->assertSee(route('car-landing.model', ['lada', '2107']));
    }

    public function test_generation_table_strips_model_and_skips_duplicate_rows(): void
    {
        $html = $this->get(route('car-landing.model', ['lada', 'vesta']))->getContent();

        preg_match('#<tbody>(.*?)</tbody>#s', $html, $table);

        $this->assertSame(4, substr_count($table[1], '<tr>'));
        $this->assertSame(1, substr_count($table[1], '<td>I 2015 - 2022</td>'));
        $this->assertStringContainsString('<td>Все годы</td>', $table[1]);
        $this->assertStringContainsString('<td>I Рестайлинг 2022 - н.в.</td>', $table[1]);
        $this->assertStringContainsString('<td>I 2015 - 2022 1.8</td>', $table[1]);
        // Две записи одного поколения — одна строка с объединёнными значениями по возрастанию.
        $this->assertMatchesRegularExpression('#<td>I 2015 - 2022</td>\s*<td>55, 60, 62</td>\s*<td>Обратная</td>\s*<td>\s*<ul class="car-landing__dims">\s*<li class="car-landing__dim">242×175×190</li>\s*<li class="car-landing__dim">246×175×190</li>#u', $table[1]);
    }

    public function test_polarity_lists_are_shown_without_universal(): void
    {
        $this->fitment('2107', 'ВАЗ (Lada) 2107 1982 - 2012', 'Прямая, Универсальная', '60 Ач', engine: '1.5 бензин');

        $this->get(route('car-landing.model', ['lada', '2107']))
            ->assertOk()
            ->assertSee('<div class="car-landing__spec-value">Прямая</div>', false)
            ->assertDontSee('Универсальная');
    }

    public function test_pages_without_products_disabled_brands_and_skipped_models_are_not_found(): void
    {
        $this->get(route('car-landing.model', ['lada', 'largus']))->assertNotFound();
        $this->get(route('car-landing.model', ['lada', 'xray-concept']))->assertNotFound();
        $this->get(route('car-landing.model', ['lada', 'net-takoy']))->assertNotFound();
        // Группы спецтехники в посадочные не попадают, даже если АКБ подходят.
        BatteryFitment::query()->create(['brand' => 'Грузовики', 'model' => 'КамАЗ', 'capacity' => '60 Ач', 'polarity' => 'Обратная']);
        $this->get(route('car-landing.brand', 'gruzoviki'))->assertNotFound();
    }

    public function test_generation_pages_with_own_products_and_navigation(): void
    {
        $generationUrl = route('car-landing.generation', ['lada', '2131-4x4', 'i-1993-2019']);

        $this->get(route('car-landing.model', ['lada', '2131-4x4']))
            ->assertOk()
            ->assertSeeInOrder(['Все поколения', 'I 1993 - 2019', 'I Рестайлинг 2019 - 2021'])
            ->assertSee($generationUrl);

        $this->get($generationUrl)
            ->assertOk()
            ->assertSee('<title>Аккумулятор для Lada 2131 (4x4) I 1993 - 2019 — купить в Уфе | Старт-Стоп</title>', false)
            ->assertSee('<h1>Аккумулятор для Lada 2131 (4x4) I 1993 - 2019</h1>', false)
            ->assertSee('<link rel="canonical" href="'.$generationUrl.'">', false)
            ->assertSee('TITAN 60Ah П.П.')
            ->assertDontSee('ZUBR 62Ah О.П.')
            ->assertSee('car-landing__chip car-landing__chip_active">I 1993 - 2019', false);
    }

    public function test_generation_with_same_products_as_model_points_canonical_to_model(): void
    {
        $this->get(route('car-landing.generation', ['lada', 'vesta', 'i-2015-2022']))
            ->assertOk()
            ->assertSee('<h1>Аккумулятор для Lada Vesta I 2015 - 2022</h1>', false)
            ->assertSee('<link rel="canonical" href="'.route('car-landing.model', ['lada', 'vesta']).'">', false);
    }

    public function test_selector_leads_to_landing_pages(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<a href="'.route('car-landing.index').'">Аккумуляторы для всех марок и моделей →</a>', false);

        // Поколения с посадочными страницами: без марки в подписи, по годам, со ссылками.
        $this->getJson(route('battery-filter.generations', ['brand' => 'ВАЗ (Lada)', 'model' => '2131 (4x4)']))
            ->assertOk()
            ->assertJsonPath('0.name', 'I 1993 - 2019')
            ->assertJsonPath('0.url', route('car-landing.generation', ['lada', '2131-4x4', 'i-1993-2019']))
            ->assertJsonPath('1.name', 'I Рестайлинг 2019 - 2021')
            ->assertJsonCount(2);

        // Одно поколение — сразу страница модели.
        $this->getJson(route('battery-filter.generations', ['brand' => 'ВАЗ (Lada)', 'model' => '2107']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.url', route('car-landing.model', ['lada', '2107']));

        $this->get(route('battery-selection', ['brand' => 'ВАЗ (Lada)', 'model' => 'Vesta', 'gen' => 'ВАЗ (Lada) Vesta I 2015 - 2022']))
            ->assertOk()
            ->assertSee('Все аккумуляторы для Lada Vesta')
            ->assertSee(route('car-landing.model', ['lada', 'vesta']));
    }

    public function test_calculation_is_cached_until_nightly_refresh(): void
    {
        // Как Redis: значения сериализуются, объекты обратно не восстанавливаются.
        config(['cache.stores.array.serialize' => true]);

        $this->assertSame(9, app(CarLandingCatalog::class)->refresh());

        // Новый аккумулятор под 2107 днём на страницах не появляется…
        $this->battery('AKOM 60Ah П.П.', ['Прямая', '55 - 65 Ah', 'Евро L2 (242 x 175 x 190 мм)'], price: 6000);

        $catalog = app(CarLandingCatalog::class);
        $model = $catalog->model($catalog->brand('lada'), '2107');
        $this->assertSame('Lada 2107', $model->fullName());
        $this->assertCount(1, $catalog->productIds($model));

        // …а после ночного пересчёта — да.
        $this->artisan('car-landings:refresh')->expectsOutput('Посадочные страницы пересчитаны: 9')->assertSuccessful();

        $catalog = app(CarLandingCatalog::class);
        $this->assertCount(2, $catalog->productIds($catalog->model($catalog->brand('lada'), '2107')));
    }

    public function test_sitemap_includes_landing_pages(): void
    {
        $path = storage_path('framework/testing/sitemap-car.xml');

        $this->artisan('sitemap:generate', ['--path' => $path])->assertSuccessful();

        $xml = file_get_contents($path);
        unlink($path);

        $this->assertStringContainsString('<loc>'.route('car-landing.brand', 'lada').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('car-landing.model', ['lada', 'vesta']).'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('car-landing.generation', ['lada', '2131-4x4', 'i-1993-2019']).'</loc>', $xml);
        // Поколение с тем же набором, что у модели, — копия её страницы.
        $this->assertStringNotContainsString('vesta/i-2015-2022', $xml);
        $this->assertStringNotContainsString('largus', $xml);
    }

    /**
     * @param  list<string>  $values
     */
    private function attribute(int $id, string $name, array $values): void
    {
        $attribute = Attribute::query()->forceCreate(['id' => $id, 'name' => $name]);
        $attribute->values()->createMany(array_map(fn (string $value) => ['value' => $value], $values));
    }

    private function fitment(string $model, ?string $generation, string $polarity, string $capacity, string $dims = '242x175x190', ?string $engine = null): void
    {
        BatteryFitment::query()->create([
            'brand' => 'ВАЗ (Lada)',
            'model' => $model,
            'generation' => $generation,
            'engine' => $engine,
            'capacity' => $capacity,
            'polarity' => $polarity,
            'dims' => $dims,
        ]);
    }

    /**
     * @param  list<string>  $values
     */
    private function battery(string $name, array $values, int $price, int $quantity = 0): void
    {
        $product = Product::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'price' => $price,
            'quantity' => $quantity,
            'status' => true,
        ]);

        $product->attributeValues()->attach(AttributeValue::query()->whereIn('value', $values)->pluck('id'));
        $product->categories()->attach($this->batteries);
    }
}
