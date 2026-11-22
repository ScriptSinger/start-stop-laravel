<?php

namespace Tests\Feature;

use App\Console\Commands\CleanDimensions;
use App\Models\Attribute;
use App\Models\Page;
use App\Models\Product;
use App\Models\TradeInType;
use App\Services\Catalog\TradeInTypeMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Rap2hpoutre\FastExcel\FastExcel;
use Tests\TestCase;

class BatteryTradeInTest extends TestCase
{
    use RefreshDatabase;

    private Attribute $dimensions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dimensions = Attribute::query()->create(['name' => 'Габариты']);
        config(['shop.battery_fitment.attributes.dimensions' => $this->dimensions->id]);
    }

    /**
     * @return array<string, array{string, ?array{int, int, int}}>
     */
    public static function sizeTexts(): array
    {
        return [
            'название типа со скобками' => ['Евро L3 (278 x 175 x 190 мм)', [278, 175, 190]],
            'размеры числами' => ['277x175x190', [277, 175, 190]],
            'кириллическая х и ×' => ['277х175×190', [277, 175, 190]],
            'размеры в описании' => ['65-750 (302x182x192)', [302, 182, 192]],
            'без размеров' => ['Груз B (180 - 190 Ah)', null],
        ];
    }

    /**
     * @param  ?array{int, int, int}  $expected
     */
    #[DataProvider('sizeTexts')]
    public function test_dimensions_are_parsed_from_value_text(string $text, ?array $expected): void
    {
        $this->assertSame($expected, TradeInTypeMatcher::parseDimensions($text));
    }

    public function test_discount_comes_only_from_the_product_type_field(): void
    {
        $type = $this->type('L3', 1200, [278, 175, 190]);
        $typed = $this->product('С типом');
        $typed->update(['trade_in_type_id' => $type->id]);
        $withSizesOnly = $this->productWithSize('278x175x190', 'Только габариты');

        $this->assertSame(1200.0, $typed->fresh('tradeInType')->tradeInDiscount());
        $this->assertTrue($typed->fresh('tradeInType')->hasTradeIn());
        $this->assertSame(5800.0, $typed->fresh('tradeInType')->priceFor(true));
        $this->assertSame(0.0, $withSizesOnly->fresh(['tradeInType', 'attributeValues'])->tradeInDiscount(), 'Габариты скидку не дают, только поле «Тип корпуса»');
    }

    public function test_type_without_amount_inactive_type_or_no_type_give_no_discount(): void
    {
        $noAmount = $this->product('A');
        $noAmount->update(['trade_in_type_id' => $this->type('LB2', null)->id]);
        $inactive = $this->product('B');
        $inactive->update(['trade_in_type_id' => $this->type('L1', 700)->id]);
        TradeInType::query()->where('code', 'L1')->update(['is_active' => false]);
        $none = $this->product('C');

        foreach ([$noAmount, $inactive, $none] as $product) {
            $fresh = $product->fresh('tradeInType');
            $this->assertSame(0.0, $fresh->tradeInDiscount());
            $this->assertFalse($fresh->hasTradeIn());
            $this->assertSame(7000.0, $fresh->priceFor(true));
        }
    }

    public function test_changing_the_type_amount_changes_all_its_products_at_once(): void
    {
        $type = $this->type('L3', 1200, [278, 175, 190]);
        $first = $this->product('A');
        $second = $this->product('B');
        $first->update(['trade_in_type_id' => $type->id]);
        $second->update(['trade_in_type_id' => $type->id]);

        $type->update(['amount' => 1100]);

        $this->assertSame(1100.0, $first->fresh('tradeInType')->tradeInDiscount());
        $this->assertSame(1100.0, $second->fresh('tradeInType')->tradeInDiscount());
    }

    public function test_deleting_a_type_clears_it_on_products_instead_of_deleting_them(): void
    {
        $type = $this->type('L3', 1200);
        $product = $this->product('A');
        $product->update(['trade_in_type_id' => $type->id]);

        $type->delete();

        $this->assertNull($product->fresh()->trade_in_type_id);
        $this->assertSame(0.0, $product->fresh('tradeInType')->tradeInDiscount());
    }

    public function test_matcher_finds_type_by_range_with_tolerance(): void
    {
        $this->type('L3', 1200, [278, 175, 190]);
        $matcher = app(TradeInTypeMatcher::class);

        foreach (['278x175x190', '276x173x188', '280x177x192'] as $size) {
            $this->assertSame('L3', $matcher->typeFor([$size])?->code, "{$size} должен попасть в L3");
        }

        foreach (['275x175x190', '281x175x190', '278x172x190', '278x175x193'] as $size) {
            $this->assertNull($matcher->typeFor([$size]), "{$size} вне диапазона L3");
        }
    }

    public function test_matcher_prefers_type_code_in_the_name_and_does_not_confuse_lb_with_l(): void
    {
        $this->type('L3', 1200, [278, 175, 190]);
        $this->type('Груз B', 2000);
        $matcher = app(TradeInTypeMatcher::class);

        $this->assertSame('L3', $matcher->typeFor(['Евро L3 (300 x 100 x 100 мм)'])?->code, 'Код важнее размеров');
        $this->assertNull($matcher->typeFor(['Евро LB3 (278 x 175 x 175 мм)']), 'LB3 — не L3');
        $this->assertSame('Груз B', $matcher->typeFor(['Груз B (180 - 220 Ah)'])?->code, 'Грузовые по коду в названии');
        $this->assertNull($matcher->typeFor(['513x223x223']), 'Размеры грузового числами: решает менеджер');
    }

    public function test_matcher_returns_nothing_for_a_size_in_two_types_or_for_inactive_type(): void
    {
        $this->type('A', 1000, [200, 150, 150]);
        $this->type('B', 2000, [202, 150, 150]);
        $this->type('C', 3000, [300, 150, 150])->update(['is_active' => false]);
        $matcher = app(TradeInTypeMatcher::class);

        $this->assertNull($matcher->typeFor(['201x150x150']), 'Подходит двум типам');
        $this->assertSame('A', $matcher->typeFor(['198x150x150'])?->code);
        $this->assertNull($matcher->typeFor(['300x150x150']), 'Тип выключен');
    }

    public function test_assign_command_dry_run_changes_nothing(): void
    {
        $this->type('L3', 1200, [278, 175, 190]);
        $product = $this->productWithSize('277x175x190');

        $this->artisan('trade-in:assign-types')->expectsOutputToContain('Пробный прогон')->assertSuccessful();

        $this->assertNull($product->fresh()->trade_in_type_id);
    }

    public function test_assign_command_sets_types_by_code_and_range_without_touching_set_or_unmatched(): void
    {
        $l3 = $this->type('L3', 1200, [278, 175, 190]);
        $truck = $this->type('Груз B', 2000);
        $other = $this->type('L1', 700, [207, 175, 190]);
        $byRange = $this->productWithSize('277x175x190', 'По размерам');
        $byCode = $this->productWithSize('Груз B (180 - 190 Ah)', 'По коду');
        $manual = $this->productWithSize('278x175x190', 'Вручную');
        $manual->update(['trade_in_type_id' => $other->id]);
        $unmatched = $this->productWithSize('513x223x223', 'Не подобрался');
        $noDimensions = $this->product('Без габаритов');

        $this->artisan('trade-in:assign-types --force')->assertSuccessful();

        $this->assertSame($l3->id, $byRange->fresh()->trade_in_type_id);
        $this->assertSame($truck->id, $byCode->fresh()->trade_in_type_id);
        $this->assertSame($other->id, $manual->fresh()->trade_in_type_id, 'Заданный вручную тип не меняется');
        $this->assertNull($unmatched->fresh()->trade_in_type_id);
        $this->assertNull($noDimensions->fresh()->trade_in_type_id);
    }

    public function test_clean_command_sets_types_first_then_leaves_only_sizes_in_dimensions(): void
    {
        $a = $this->type('Груз A', 1700);
        $b = $this->type('Груз B', 2000);
        $b19 = $this->type('B19', 500, [187, 127, 227]);
        $lb2 = $this->type('LB2', null, [242, 175, 175]);
        $truckA = $this->productWithSize('Груз A (130 - 140 Ah)', 'Грузовой A');
        $truckB = $this->productWithSize('Груз B (180 - 190 Ah)', 'Грузовой B');
        $truckB->update(['trade_in_type_id' => $a->id]);
        $asia = $this->productWithSize('Азия B19 (187 x 127 x 227 мм)', 'Азия');
        $low = $this->productWithSize('Евро LB2 (242 x 175 x 175 мм)', 'Низкий');
        $numeric = $this->productWithSize('278x175x190', 'Размеры числами');
        $before = $this->dimensions->values()->count();

        $this->artisan('trade-in:clean-dimensions')->expectsOutputToContain('Пробный прогон')->assertSuccessful();
        $this->assertSame($before, $this->dimensions->values()->count(), 'Пробный прогон ничего не меняет');
        $this->assertNull($asia->fresh()->trade_in_type_id);

        $this->artisan('trade-in:clean-dimensions --force')->assertSuccessful();

        $this->assertSame($a->id, $truckA->fresh()->trade_in_type_id, 'Тип проставлен до очистки');
        $this->assertSame($a->id, $truckB->fresh()->trade_in_type_id, 'Заданный вручную тип не меняется');
        $this->assertSame($b19->id, $asia->fresh()->trade_in_type_id);
        $this->assertSame($lb2->id, $low->fresh()->trade_in_type_id, 'Тип без суммы тоже сохраняется');
        $this->assertSame(0, $this->dimensions->values()->where('value', 'like', '%Ah%')->count(), 'Значения с ёмкостью удалены');
        $this->assertSame(0, $truckA->attributeValues()->count(), 'У грузовых габаритов нет');
        $this->assertSame(['187 x 127 x 227'], $asia->attributeValues()->pluck('value')->all(), 'Остались только размеры, без «мм»');
        $this->assertSame(['242 x 175 x 175'], $low->attributeValues()->pluck('value')->all());
        $this->assertSame(['278 x 175 x 190'], $numeric->attributeValues()->pluck('value')->all(), 'Размеры без типа приведены к единому виду');
        $this->assertSame(1700.0, $truckA->fresh('tradeInType')->tradeInDiscount(), 'Скидка из типа и без габаритов');
        $this->assertSame(500.0, $asia->fresh('tradeInType')->tradeInDiscount());
    }

    public function test_clean_command_leaves_value_on_products_whose_type_cannot_be_determined(): void
    {
        $this->type('L3', 1200, [278, 175, 190]);
        $orphanTruck = $this->productWithSize('Груз C (220 - 225 Ah)', 'Нет типа Груз C');
        $orphanAsia = $this->productWithSize('Азия Z99 (100 x 100 x 100 мм)', 'Нет типа Z99');

        $this->artisan('trade-in:clean-dimensions --force')->assertSuccessful();

        $this->assertSame(['Груз C (220 - 225 Ah)'], $orphanTruck->attributeValues()->pluck('value')->all(), 'Без типа значение не снимается');
        $this->assertSame(['Азия Z99 (100 x 100 x 100 мм)'], $orphanAsia->attributeValues()->pluck('value')->all(), 'Без типа значение не переименовывается');
        $this->assertNull($orphanTruck->fresh()->trade_in_type_id);
    }

    public function test_clean_command_merges_the_same_size_written_twice_into_one_value(): void
    {
        $this->type('D26', 1200, [260, 173, 225]);
        $withType = $this->productWithSize('Азия D26 (260 x 173 x 225 мм)', 'С типом в названии');
        $plain = $this->productWithSize('260x173x225', 'Просто размеры');
        $both = $this->productWithSize('260 x 173 x 225 мм', 'С мм');

        $this->artisan('trade-in:clean-dimensions --force')->assertSuccessful();

        $values = $this->dimensions->values()->pluck('value')->all();
        $this->assertSame(['260 x 173 x 225'], $values, 'Один размер — одно значение');
        foreach ([$withType, $plain, $both] as $product) {
            $this->assertSame(['260 x 173 x 225'], $product->attributeValues()->pluck('value')->all());
        }
        $this->assertNotNull($withType->fresh()->trade_in_type_id, 'Тип проставлен до слияния');
    }

    public function test_clean_command_keeps_only_sizes_when_they_are_mixed_with_other_text(): void
    {
        $product = $this->productWithSize('65-750 (302x182x192)', 'Смешанные данные');
        $this->dimensions->values()->create(['value' => 'Другие']);

        $this->artisan('trade-in:clean-dimensions')->expectsOutputToContain('вперемешку с текстом')->assertSuccessful();
        $this->artisan('trade-in:clean-dimensions --force')->assertSuccessful();

        $this->assertSame(['302 x 182 x 192'], $product->attributeValues()->pluck('value')->all());
        $this->assertTrue($this->dimensions->values()->where('value', 'Другие')->exists(), 'Значения без размеров не трогаются');
    }

    public function test_clean_command_adds_the_unit_to_the_dimensions_attribute_name(): void
    {
        $this->assertSame('Габариты', $this->dimensions->name);
        $this->productWithSize('278x175x190');

        $this->artisan('trade-in:clean-dimensions')->assertSuccessful();
        $this->assertSame('Габариты', $this->dimensions->fresh()->name, 'Пробный прогон ничего не меняет');

        $this->artisan('trade-in:clean-dimensions --force')->assertSuccessful();
        $this->assertSame('Габариты (мм)', $this->dimensions->fresh()->name);

        $this->artisan('trade-in:clean-dimensions --force')->assertSuccessful();
        $this->assertSame('Габариты (мм)', $this->dimensions->fresh()->name, 'Повторный запуск ничего не ломает');
    }

    public function test_split_type_and_size(): void
    {
        $this->assertSame(['B19', '187 x 127 x 227'], CleanDimensions::splitTypeAndSize('Азия B19 (187 x 127 x 227 мм)'));
        $this->assertSame(['LB2', '242 x 175 x 175'], CleanDimensions::splitTypeAndSize('Евро LB2 (242 x 175 x 175 мм)'));
        $this->assertNull(CleanDimensions::splitTypeAndSize('278x175x190'));
        $this->assertNull(CleanDimensions::splitTypeAndSize('Груз A (130 - 140 Ah)'));
        $this->assertNull(CleanDimensions::splitTypeAndSize('65-750 (302x182x192)'));
    }

    public function test_assign_command_second_run_changes_nothing(): void
    {
        $this->type('L3', 1200, [278, 175, 190]);
        $product = $this->productWithSize('277x175x190');

        $this->artisan('trade-in:assign-types --force')->assertSuccessful();
        TradeInType::query()->where('code', 'L3')->update(['code' => 'L3X']);
        $product->update(['trade_in_type_id' => null]);
        $this->artisan('trade-in:assign-types --force')->assertSuccessful();

        $this->assertNotNull($product->fresh()->trade_in_type_id, 'Повторный запуск назначает только пустым');
    }

    public function test_fill_types_dry_run_changes_nothing_and_force_creates_the_start_table(): void
    {
        $this->artisan('trade-in:fill-types')->expectsOutputToContain('Пробный прогон')->assertSuccessful();
        $this->assertSame(0, TradeInType::query()->count());

        $this->artisan('trade-in:fill-types --force')->assertSuccessful();

        $this->assertSame(21, TradeInType::query()->count());
        $l2 = TradeInType::query()->where('code', 'L2')->firstOrFail();
        $this->assertSame('1000.00', $l2->amount);
        $this->assertSame([240, 244, 173, 177, 188, 192], [$l2->length_min, $l2->length_max, $l2->width_min, $l2->width_max, $l2->height_min, $l2->height_max]);

        $truck = TradeInType::query()->where('code', 'Груз C')->firstOrFail();
        $this->assertNull($truck->length_min, 'У грузовых размеров нет: тип по коду в «Габаритах»');
        $this->assertSame('2500.00', $truck->amount);
    }

    public function test_fill_types_second_run_does_not_overwrite_existing_types(): void
    {
        $this->artisan('trade-in:fill-types --force')->assertSuccessful();
        TradeInType::query()->where('code', 'L2')->update(['amount' => 1234, 'length_max' => 250]);

        $this->artisan('trade-in:fill-types --force')->assertSuccessful();

        $l2 = TradeInType::query()->where('code', 'L2')->firstOrFail();
        $this->assertSame('1234.00', $l2->amount);
        $this->assertSame(250, $l2->length_max);
        $this->assertSame(21, TradeInType::query()->count());
    }

    public function test_public_page_table_is_built_from_the_types(): void
    {
        $this->type('L3', 1200, [278, 175, 190])->update(['name' => 'L3 - Евро до 77 Ah', 'sort_order' => 2]);
        $this->type('B19', 500, [187, 127, 227])->update(['name' => 'B19 - Азия до 40 Ah', 'sort_order' => 1]);
        $this->type('LB2', null, [242, 175, 175]);
        $this->type('X', 900, [100, 100, 100])->update(['is_active' => false, 'name' => 'Скрытый тип']);
        Page::query()->create(['title' => 'Трейд-ин', 'slug' => 'trade-in', 'status' => true, 'description' => '<p>Скидки:</p><p>[[block:trade-in]]</p><p>Конец</p>']);

        $html = $this->get(route('page.show', 'trade-in'))->assertOk()->getContent();

        $this->assertStringContainsString('<table class="trade-in-table">', $html);
        $this->assertLessThan(strpos($html, 'L3 - Евро до 77 Ah'), strpos($html, 'B19 - Азия до 40 Ah'), 'По порядку');
        $this->assertStringNotContainsString('LB2', $html);
        $this->assertStringNotContainsString('Скрытый тип', $html);
        $this->assertStringNotContainsString('[[block:trade-in]]', $html);
        $this->assertStringContainsString('Конец', $html);
    }

    public function test_trade_in_page_always_shows_the_table_from_types_and_drops_the_hand_made_one(): void
    {
        $this->type('L2', 1000, [242, 175, 190])->update(['name' => 'L2 - Евро до 65 Ah', 'sort_order' => 1]);
        $page = Page::query()->create(['title' => 'Трейд-ин', 'slug' => 'trade-in', 'status' => true, 'description' => '<p>Скидка при сдаче.</p><table><tr><th>Тип АКБ</th><th>Скидка</th></tr><tr><td>L2 - до 65 Ah (старое)</td><td>999 руб.</td></tr></table><p>Условия.</p>']);

        $html = $this->get(route('page.show', 'trade-in'))->assertOk()->getContent();
        $this->assertStringContainsString('L2 - Евро до 65 Ah', $html);
        $this->assertStringNotContainsString('(старое)', $html, 'Ручная таблица не выводится');
        $this->assertStringContainsString('Скидка при сдаче.', $html);
        $this->assertStringContainsString('Условия.', $html);
        $this->assertLessThan(strpos($html, 'Условия.'), strpos($html, 'L2 - Евро до 65 Ah'), 'Таблица на месте прежней');

        // Изменили сумму в админке: страница показывает новое значение без правок текста страницы.
        TradeInType::query()->where('code', 'L2')->update(['amount' => 1100]);
        $this->get(route('page.show', 'trade-in'))->assertSee('1 100');
    }

    public function test_trade_in_page_without_any_table_gets_it_at_the_end(): void
    {
        $this->type('L2', 1000, [242, 175, 190])->update(['name' => 'L2 - Евро до 65 Ah']);
        Page::query()->create(['title' => 'Трейд-ин', 'slug' => 'trade-in', 'status' => true, 'description' => '<p>Только текст.</p>']);

        $this->get(route('page.show', 'trade-in'))->assertSee('Только текст.')->assertSee('L2 - Евро до 65 Ah');
    }

    public function test_other_pages_keep_their_own_tables(): void
    {
        $this->type('L2', 1000, [242, 175, 190])->update(['name' => 'L2 - Евро до 65 Ah']);
        Page::query()->create(['title' => 'Другая', 'slug' => 'drugaya', 'status' => true, 'description' => '<table><tr><th>Тип АКБ</th></tr><tr><td>ручная</td></tr></table>']);

        $this->get(route('page.show', 'drugaya'))->assertSee('ручная')->assertDontSee('L2 - Евро до 65 Ah');
    }

    public function test_admin_crud_creates_edits_and_deletes_types_and_validates_ranges(): void
    {
        $this->admin();
        $base = '/admin/resource/trade-in-resource';

        $this->get($base.'/trade-in-index-page')->assertOk()->assertSee('Трейд-ин');
        $this->get($base.'/trade-in-form-page')->assertOk()->assertSee('name="amount"', false)->assertSee('name="length_min"', false)->assertDontSee('capacity', false);

        $this->post($base.'/crud', ['code' => 'L7', 'name' => 'L7 - Евро', 'amount' => 1600, 'length_min' => 400, 'length_max' => 396, 'width_min' => 173, 'width_max' => 177, 'height_min' => 188, 'height_max' => 192, 'sort_order' => 0, 'is_active' => 1])
            ->assertSessionHasErrors('length_max', errorBag: 'trade-in-resource');

        $this->post($base.'/crud', ['code' => 'L7', 'name' => 'L7 - Евро', 'amount' => 1600, 'length_min' => 393, 'length_max' => 397, 'width_min' => 173, 'width_max' => 177, 'height_min' => 188, 'height_max' => 192, 'sort_order' => 0, 'is_active' => 1])
            ->assertRedirect();

        $type = TradeInType::query()->where('code', 'L7')->firstOrFail();
        $this->assertSame('1600.00', $type->amount);

        $this->post($base.'/crud', ['code' => 'L7', 'name' => 'Дубль', 'sort_order' => 0])->assertSessionHasErrors('code', errorBag: 'trade-in-resource');

        $this->put("{$base}/crud/{$type->id}", ['code' => 'L7', 'name' => 'L7 - Евро', 'amount' => 1700, 'sort_order' => 0, 'is_active' => 1])->assertRedirect();
        $this->assertSame('1700.00', $type->fresh()->amount);

        $this->delete("{$base}/crud/{$type->id}")->assertRedirect();
        $this->assertDatabaseMissing('trade_in_types', ['id' => $type->id]);
    }

    public function test_admin_list_shows_size_ranges_and_updates_amount_and_activity_inline(): void
    {
        $this->admin();
        $l3 = $this->type('L3', 1200, [278, 175, 190]);
        $truck = $this->type('Груз B', 2000);

        $this->get('/admin/component/trade-in-index-page/trade-in-resource?'.http_build_query(['_component_name' => 'index-table-trade-in-resource']))
            ->assertOk()
            ->assertSee('Диапазон размеров, мм (Д × Ш × В)')
            ->assertDontSee('Ёмкость')
            ->assertSee('276–280')
            ->assertSee('173–177')
            ->assertSee('188–192')
            ->assertSee('badge', false)
            ->assertDontSee('276–280 × 173–177');

        $this->put("/admin/update-field/column/trade-in-resource/{$l3->id}", ['field' => 'amount', 'value' => 1500])->assertSuccessful();
        $this->assertSame('1500.00', $l3->fresh()->amount);

        $this->put("/admin/update-field/column/trade-in-resource/{$truck->id}", ['field' => 'is_active', 'value' => 0])->assertSuccessful();
        $this->assertFalse($truck->fresh()->is_active);
    }

    public function test_admin_list_sorts_by_columns_and_filters_by_discount_activity_sizes_and_range(): void
    {
        $this->admin();
        $b = $this->type('B19', 500, [187, 127, 227]);
        $this->type('L3', 1200, [278, 175, 190]);
        $this->type('Груз B', 2000);
        $this->type('LB2', null, [242, 175, 175])->update(['is_active' => false]);
        $b->update(['name' => 'Азия малая']);

        $table = fn (array $query = []) => $this->get('/admin/component/trade-in-index-page/trade-in-resource?'.http_build_query(['_component_name' => 'index-table-trade-in-resource', ...$query]))->assertOk();

        // Сортировка.
        $table(['sort' => '-amount'])->assertSeeInOrder(['Груз B', 'L3', 'B19']);
        $table(['sort' => 'code'])->assertSeeInOrder(['B19', 'L3']);
        // По длине: B19 (185) < LB2 (240) < L3 (276), грузовые без размеров в конце (ключ — колонка поля, у вычисляемых это id).
        $table(['sort' => 'id'])->assertSeeInOrder(['B19', 'LB2', 'L3', 'Груз B']);
        $table(['sort' => '-id'])->assertSeeInOrder(['L3', 'LB2', 'B19']);

        // Фильтры.
        $table(['filter' => ['discount' => 'empty']])->assertSee('LB2')->assertDontSee('L3')->assertDontSee('B19');
        $table(['filter' => ['discount' => 'set']])->assertSee('L3')->assertDontSee('LB2');
        $table(['filter' => ['activity' => '0']])->assertSee('LB2')->assertDontSee('L3');
        $table(['filter' => ['activity' => '1']])->assertSee('L3')->assertDontSee('LB2');
        $table(['filter' => ['sizes' => 'none']])->assertSee('Груз B')->assertDontSee('L3');
        $table(['filter' => ['sizes' => 'set']])->assertSee('L3')->assertDontSee('Груз B');
        $table(['filter' => ['amount_from' => 1000, 'amount_to' => 1500]])->assertSee('L3')->assertDontSee('B19')->assertDontSee('Груз B');
    }

    public function test_product_form_has_type_select_instead_of_own_discount_and_saves_it(): void
    {
        $this->admin();
        $type = $this->type('L3', 1200, [278, 175, 190]);
        $product = $this->product('TITAN');

        $this->get("/admin/resource/product-resource/product-form-page/{$product->id}")
            ->assertOk()
            ->assertSee('name="trade_in_type_id"', false)
            ->assertDontSee('name="trade_in_discount"', false)
            ->assertSee('L3');

        $this->put("/admin/resource/product-resource/crud/{$product->id}", ['name' => 'TITAN', 'slug' => $product->slug, 'price' => 7000, 'quantity' => 0, 'trade_in_type_id' => $type->id])->assertRedirect();

        $this->assertSame($type->id, $product->fresh()->trade_in_type_id);
    }

    public function test_product_page_shows_case_type_as_its_own_row_after_dimensions(): void
    {
        $type = $this->type('L3', 1200, [278, 175, 190]);
        $product = $this->productWithSize('278 x 175 x 190 мм', 'С габаритами');
        $product->update(['trade_in_type_id' => $type->id, 'status' => true]);
        $truck = $this->type('Груз A', 1700);
        $noSizes = $this->product('Без габаритов');
        $noSizes->update(['trade_in_type_id' => $truck->id, 'status' => true]);
        $noSizes->attributeValues()->attach(Attribute::query()->create(['name' => 'Напряжение'])->values()->create(['value' => '12V']));
        $noType = $this->productWithSize('300 x 100 x 100 мм', 'Без типа');
        $noType->update(['status' => true]);

        $html = $this->get(route('product.show', $product))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'Тип корпуса'), 'В кратком списке и во вкладке');
        $this->assertLessThan(strpos($html, 'Тип корпуса'), strpos($html, '278 x 175 x 190 мм'), 'Сразу после габаритов');

        $this->get(route('product.show', $noSizes))->assertOk()->assertSee('Тип корпуса')->assertSee('Груз A');
        $this->get(route('product.show', $noType))->assertOk()->assertDontSee('Тип корпуса');
    }

    public function test_product_list_and_excel_show_dimensions_and_case_type_as_separate_columns(): void
    {
        $this->admin();
        $type = $this->type('L3', 1200, [278, 175, 190]);
        $product = $this->productWithSize('278 x 175 x 190 мм', 'Размеры и тип');
        $product->update(['trade_in_type_id' => $type->id]);

        $this->get('/admin/component/product-index-page/product-resource?'.http_build_query(['_component_name' => 'index-table-product-resource']))
            ->assertOk()
            ->assertSee('Габариты (мм)')
            ->assertSee('Тип корпуса')
            ->assertSee('278 x 175 x 190 мм')
            ->assertDontSee('L3 278');

        $response = $this->get('/admin/resource/product-resource/handler/export-handler?'.http_build_query(['filter' => ['name' => 'Размеры и тип']]));
        $rows = (new FastExcel)->import($response->baseResponse->getFile()->getPathname());
        $this->assertSame('278 x 175 x 190 мм', $rows[0]['Габариты (мм)']);
        $this->assertSame('L3', $rows[0]['Тип корпуса']);
    }

    public function test_product_list_filters_by_type_and_by_missing_type(): void
    {
        $this->admin();
        $type = $this->type('L3', 1200);
        $with = $this->product('Со типом');
        $with->update(['trade_in_type_id' => $type->id]);
        $this->product('Без типа');

        $table = fn (string $value) => $this->get('/admin/component/product-index-page/product-resource?'.http_build_query(['_component_name' => 'index-table-product-resource', 'filter' => ['trade_in_type' => $value]]));

        $table((string) $type->id)->assertOk()->assertSee('Со типом')->assertDontSee('Без типа');
        $table('none')->assertOk()->assertSee('Без типа')->assertDontSee('Со типом');
    }

    /**
     * @param  ?array{int, int, int}  $size
     */
    private function type(string $code, ?float $amount, ?array $size = null): TradeInType
    {
        return TradeInType::query()->create([
            'code' => $code,
            'name' => $code,
            'amount' => $amount,
            'length_min' => $size ? $size[0] - 2 : null, 'length_max' => $size ? $size[0] + 2 : null,
            'width_min' => $size ? $size[1] - 2 : null, 'width_max' => $size ? $size[1] + 2 : null,
            'height_min' => $size ? $size[2] - 2 : null, 'height_max' => $size ? $size[2] + 2 : null,
        ]);
    }

    private function product(string $name = 'TITAN'): Product
    {
        return Product::query()->create(['name' => $name, 'slug' => str($name)->slug()->value().'-'.uniqid(), 'price' => 7000, 'status' => true]);
    }

    private function productWithSize(string $size, string $name = 'TITAN'): Product
    {
        $product = $this->product($name);
        $product->attributeValues()->attach($this->dimensions->values()->firstOrCreate(['value' => $size]));

        return $product->unsetRelation('attributeValues');
    }

    private function admin(): void
    {
        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]), 'moonshine');
    }
}
