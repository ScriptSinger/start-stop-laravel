<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\BatteryFitment;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatterySelectionTest extends TestCase
{
    use RefreshDatabase;

    private Category $batteries;

    protected function setUp(): void
    {
        parent::setUp();

        $this->batteries = Category::query()->forceCreate(['id' => 1, 'name' => 'Аккумуляторы', 'slug' => 'akkumulyatori']);

        // id характеристик — как в OCFilter (см. shop.battery_fitment.attributes).
        $this->attribute(13, 'Полярность', ['Обратная', 'Прямая', 'Универсальная']);
        $this->attribute(20, 'Ёмкость (Ah)', ['55 - 65 Ah', '66 - 77 Ah', '180 - 190 Ah']);
        $this->attribute(16, 'Габариты', ['Евро L2 (242 x 175 x 190 мм)', 'Евро LB2 (242 x 175 x 175 мм)', 'Евро L3 (278 x 175 x 190 мм)', 'Азия D26 (260 x 173 x 225 мм)', 'Азия B24 (234 x 127 x 227 мм)', 'Груз B (180 - 190 Ah)']);
    }

    public function test_matches_polarity_capacity_range_and_length(): void
    {
        $this->battery('Подходит', ['Обратная', '55 - 65 Ah', 'Евро L2 (242 x 175 x 190 мм)']);
        $this->battery('Универсальная полярность', ['Универсальная', '55 - 65 Ah', 'Евро L2 (242 x 175 x 190 мм)']);
        $this->battery('Прямая полярность', ['Прямая', '55 - 65 Ah', 'Евро L2 (242 x 175 x 190 мм)']);
        $this->battery('Большая ёмкость', ['Обратная', '66 - 77 Ah', 'Евро L2 (242 x 175 x 190 мм)']);
        $this->battery('Длиннее', ['Обратная', '55 - 65 Ah', 'Евро L3 (278 x 175 x 190 мм)']);
        $this->battery('Неактивный', ['Обратная', '55 - 65 Ah', 'Евро L2 (242 x 175 x 190 мм)'], ['status' => false]);
        $this->battery('Не из аккумуляторов', ['Обратная', '55 - 65 Ah', 'Евро L2 (242 x 175 x 190 мм)'], category: false);

        $this->get(route('battery-selection', ['brand' => 'Kia', 'model' => 'Rio', 'gen' => 'Kia Rio IV 2017 - 2020']))
            ->assertOk()
            ->assertSee('Аккумуляторы для Kia Rio IV 2017 - 2020')
            ->assertSee('Подходит')
            ->assertSee('Универсальная полярность')
            ->assertDontSee('Прямая полярность')
            ->assertDontSee('Большая ёмкость')
            ->assertDontSee('Длиннее')
            ->assertDontSee('Неактивный')
            ->assertDontSee('Не из аккумуляторов');
    }

    public function test_length_is_compared_with_dimensions_not_any_number(): void
    {
        // Длина машины 173 мм совпала бы с шириной «Азия D26 (260 x 173 …)» в старом коде.
        $this->fitment(['generation' => 'Узкий', 'dims' => '173x100x100']);
        $this->battery('Азия D26', ['Обратная', '55 - 65 Ah', 'Азия D26 (260 x 173 x 225 мм)']);

        $this->get(route('battery-selection', ['brand' => 'Kia', 'model' => 'Rio', 'gen' => 'Узкий']))
            ->assertOk()
            ->assertDontSee('Азия D26');
    }

    public function test_battery_taller_or_wider_than_car_compartment_is_not_offered(): void
    {
        // Низкий отсек: подходит только LB2 высотой 175, обычный L2 (190) не встанет.
        $this->fitment(['generation' => 'Низкий', 'dims' => '242x175x175']);
        $this->battery('Низкий LB2', ['Обратная', '55 - 65 Ah', 'Евро LB2 (242 x 175 x 175 мм)']);
        $this->battery('Обычный L2', ['Обратная', '55 - 65 Ah', 'Евро L2 (242 x 175 x 190 мм)']);

        $this->get(route('battery-selection', ['brand' => 'Kia', 'model' => 'Rio', 'gen' => 'Низкий']))
            ->assertOk()
            ->assertSee('Низкий LB2')
            ->assertDontSee('Обычный L2');

        // Узкий азиатский отсек 127 мм: европейский 175 мм не встанет по ширине.
        $this->fitment(['generation' => 'Узкий азиат', 'dims' => '242x127x227']);

        $this->get(route('battery-selection', ['brand' => 'Kia', 'model' => 'Rio', 'gen' => 'Узкий азиат']))
            ->assertOk()
            ->assertDontSee('Низкий LB2')
            ->assertDontSee('Обычный L2');

        // Узкий азиатский B24 (127 мм) в широкий отсек D23 (173 мм) — не держится.
        $this->fitment(['generation' => 'Азиатский D23', 'dims' => '234x173x225']);
        $this->battery('Узкий B24', ['Обратная', '55 - 65 Ah', 'Азия B24 (234 x 127 x 227 мм)']);

        $this->get(route('battery-selection', ['brand' => 'Kia', 'model' => 'Rio', 'gen' => 'Азиатский D23']))
            ->assertOk()
            ->assertDontSee('Узкий B24');

        // Обычный отсек 190 принимает и низкий аккумулятор.
        $this->get(route('battery-selection', ['brand' => 'Kia', 'model' => 'Rio', 'gen' => 'Kia Rio IV 2017 - 2020']))
            ->assertOk()
            ->assertSee('Низкий LB2')
            ->assertSee('Обычный L2');
    }

    public function test_car_accepting_both_polarities_gets_both(): void
    {
        $this->fitment(['generation' => 'Обе', 'polarity' => 'Обратная, Прямая, Универсальная']);
        $this->battery('Обратный 60', ['Обратная', '55 - 65 Ah', 'Евро L2 (242 x 175 x 190 мм)']);
        $this->battery('Прямой 60', ['Прямая', '55 - 65 Ah', 'Евро L2 (242 x 175 x 190 мм)']);

        $this->get(route('battery-selection', ['brand' => 'Kia', 'model' => 'Rio', 'gen' => 'Обе']))
            ->assertOk()
            ->assertSee('Обратный 60')
            ->assertSee('Прямой 60');
    }

    public function test_terminal_type_must_match_when_battery_has_it(): void
    {
        $this->attribute(19, 'Токовыводы', ['Стандарт конус', 'конус тонкие']);
        $this->fitment(['generation' => 'Узкие клеммы', 'terminals' => 'thin']);
        $this->battery('Тонкие клеммы', ['Обратная', '55 - 65 Ah', 'Евро L2 (242 x 175 x 190 мм)', 'конус тонкие']);
        $this->battery('Стандартные клеммы', ['Обратная', '55 - 65 Ah', 'Евро L2 (242 x 175 x 190 мм)', 'Стандарт конус']);
        $this->battery('Клеммы не указаны', ['Обратная', '55 - 65 Ah', 'Евро L2 (242 x 175 x 190 мм)']);

        $this->get(route('battery-selection', ['brand' => 'Kia', 'model' => 'Rio', 'gen' => 'Узкие клеммы']))
            ->assertOk()
            ->assertSee('Клеммы</dt>', false)
            ->assertSee('Узкие (азиатские)')
            ->assertSee('Тонкие клеммы')
            ->assertSee('Клеммы не указаны')
            ->assertDontSee('Стандартные клеммы');

        // Боковых клемм нет ни у одного товара — остаются только АКБ без «Токовыводов».
        $this->fitment(['generation' => 'Боковые клеммы', 'terminals' => 'side']);

        $this->get(route('battery-selection', ['brand' => 'Kia', 'model' => 'Rio', 'gen' => 'Боковые клеммы']))
            ->assertOk()
            ->assertSee('Клеммы не указаны')
            ->assertDontSee('Тонкие клеммы')
            ->assertDontSee('Стандартные клеммы');
    }

    public function test_engine_step_only_when_engines_need_different_batteries(): void
    {
        // Ёмкости в данных разные, но в один диапазон АКБ — шаг не нужен.
        $this->fitment(['generation' => 'Одинаковые', 'engine' => null]);
        $this->fitment(['generation' => 'Одинаковые', 'engine' => '1.4 бензин', 'capacity' => '55 Ач']);
        $this->fitment(['generation' => 'Одинаковые', 'engine' => '1.6 бензин', 'capacity' => '62 Ач']);
        // Разная полярность — шаг нужен.
        $this->fitment(['generation' => 'Разные', 'engine' => null]);
        $this->fitment(['generation' => 'Разные', 'engine' => '1.6 бензин', 'polarity' => 'Прямая']);
        $this->fitment(['generation' => 'Разные', 'engine' => '2.0 дизель']);

        $this->getJson(route('battery-filter.generations', ['brand' => 'Kia', 'model' => 'Rio']))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Одинаковые', 'engines' => false])
            ->assertJsonFragment(['name' => 'Разные', 'engines' => true])
            ->assertJsonMissing(['name' => 'Стандарт']);

        $this->getJson(route('battery-filter.engines', ['brand' => 'Kia', 'model' => 'Rio', 'gen' => 'Одинаковые']))->assertExactJson([]);
        $this->getJson(route('battery-filter.engines', ['brand' => 'Kia', 'model' => 'Rio', 'gen' => 'Разные']))->assertExactJson(['1.6 бензин', '2.0 дизель']);

        $this->getJson(route('battery-filter.result', ['brand' => 'Kia', 'model' => 'Rio', 'gen' => 'Разные', 'engine' => '1.6 бензин']))
            ->assertJsonPath('redirect', route('battery-selection', ['brand' => 'Kia', 'model' => 'Rio', 'gen' => 'Разные', 'engine' => '1.6 бензин']));

        $this->battery('Прямой 60', ['Прямая', '55 - 65 Ah', 'Евро L2 (242 x 175 x 190 мм)']);

        $this->get(route('battery-selection', ['brand' => 'Kia', 'model' => 'Rio', 'gen' => 'Разные', 'engine' => '1.6 бензин']))
            ->assertOk()
            ->assertSee('Аккумуляторы для Kia Rio Разные 1.6 бензин')
            ->assertSee('Прямой 60');

        // Без двигателя — общая запись поколения (обратная полярность).
        $this->get(route('battery-selection', ['brand' => 'Kia', 'model' => 'Rio', 'gen' => 'Разные']))
            ->assertOk()
            ->assertDontSee('Прямой 60');
    }

    public function test_unknown_polarity_does_not_filter_by_polarity(): void
    {
        $this->fitment(['generation' => 'Без полярности', 'polarity' => null]);
        $this->battery('Прямая полярность', ['Прямая', '55 - 65 Ah', 'Евро L2 (242 x 175 x 190 мм)']);

        $this->get(route('battery-selection', ['brand' => 'Kia', 'model' => 'Rio', 'gen' => 'Без полярности']))
            ->assertOk()
            ->assertSee('Прямая полярность');
    }

    public function test_capacity_without_matching_range_finds_nothing_instead_of_everything(): void
    {
        $this->fitment(['generation' => 'Грузовик', 'capacity' => '190 Ач']);
        $this->battery('Легковой', ['Обратная', '55 - 65 Ah', 'Евро L2 (242 x 175 x 190 мм)']);

        $this->get(route('battery-selection', ['brand' => 'Kia', 'model' => 'Rio', 'gen' => 'Грузовик']))
            ->assertOk()
            ->assertDontSee('Легковой')
            ->assertSee('Подходящих аккумуляторов сейчас нет');
    }

    public function test_battery_with_size_group_instead_of_dimensions_is_matched_by_capacity_and_polarity(): void
    {
        $this->fitment(['generation' => 'Грузовик', 'capacity' => '190 Ач, 180 Ач', 'dims' => '513x223x217']);
        $this->battery('Грузовой 190Ah', ['Обратная', '180 - 190 Ah', 'Груз B (180 - 190 Ah)']);
        $this->battery('Грузовой прямой', ['Прямая', '180 - 190 Ah', 'Груз B (180 - 190 Ah)']);

        $this->get(route('battery-selection', ['brand' => 'Kia', 'model' => 'Rio', 'gen' => 'Грузовик']))
            ->assertOk()
            ->assertSee('Грузовой 190Ah')
            ->assertDontSee('Грузовой прямой');
    }

    public function test_default_generation_label_finds_fitment_without_generation(): void
    {
        $this->fitment(['model' => 'Kia Rio', 'generation' => null]);

        $this->getJson(route('battery-filter.result', ['brand' => 'Kia', 'model' => 'Kia Rio', 'gen' => 'Стандарт']))
            ->assertOk()
            ->assertJsonPath('redirect', route('battery-selection', ['brand' => 'Kia', 'model' => 'Kia Rio']));

        $this->get(route('battery-selection', ['brand' => 'Kia', 'model' => 'Kia Rio', 'sort' => 'price_asc']))
            ->assertOk()
            ->assertSee('<title>Аккумуляторы для Kia Rio — Старт-Стоп</title>', false)
            ->assertSee('<link rel="canonical" href="'.e(route('battery-selection', ['brand' => 'Kia', 'model' => 'Kia Rio'])).'">', false);
    }

    public function test_selector_steps_return_models_and_generations(): void
    {
        $this->fitment();
        $this->fitment(['model' => 'Kia Rio', 'generation' => null]);

        $this->getJson(route('battery-filter.models', ['brand' => 'Kia']))
            ->assertOk()
            ->assertExactJson([['model' => 'Kia Rio'], ['model' => 'Rio']]);

        $this->getJson(route('battery-filter.generations', ['brand' => 'Kia', 'model' => 'Kia Rio']))
            ->assertOk()
            ->assertJsonPath('0.name', 'Стандарт');

        $this->getJson(route('battery-filter.generations', ['brand' => 'Kia', 'model' => 'Rio']))
            ->assertOk()
            ->assertJsonPath('0.name', 'Kia Rio IV 2017 - 2020');
    }

    public function test_fitment_without_data_is_not_found(): void
    {
        BatteryFitment::query()->create(['brand' => 'Lada', 'model' => 'Niva']);

        $this->getJson(route('battery-filter.result', ['brand' => 'Lada', 'model' => 'Niva']))
            ->assertOk()
            ->assertExactJson([]);

        $this->get(route('battery-selection', ['brand' => 'Lada', 'model' => 'Niva']))->assertNotFound();
    }

    /**
     * @param  list<string>  $values
     */
    private function attribute(int $id, string $name, array $values): void
    {
        $attribute = Attribute::query()->forceCreate(['id' => $id, 'name' => $name]);
        $attribute->values()->createMany(array_map(fn (string $value) => ['value' => $value], $values));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function fitment(array $attributes = []): BatteryFitment
    {
        return BatteryFitment::query()->create([
            'brand' => 'Kia',
            'model' => 'Rio',
            'generation' => 'Kia Rio IV 2017 - 2020',
            'capacity' => '60 Ач, 55 Ач, 62 Ач',
            'polarity' => 'Обратная',
            'dims' => '242x175x190 , 230x173x225',
            ...$attributes,
        ]);
    }

    /**
     * @param  list<string>  $values
     * @param  array<string, mixed>  $attributes
     */
    private function battery(string $name, array $values, array $attributes = [], bool $category = true): void
    {
        if (! BatteryFitment::query()->where('generation', 'Kia Rio IV 2017 - 2020')->exists()) {
            $this->fitment();
        }

        $product = Product::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->value().'-'.uniqid(),
            'price' => 7000,
            'status' => true,
            ...$attributes,
        ]);

        $product->attributeValues()->attach(AttributeValue::query()->whereIn('value', $values)->pluck('id'));

        if ($category) {
            $product->categories()->attach($this->batteries);
        }
    }
}
