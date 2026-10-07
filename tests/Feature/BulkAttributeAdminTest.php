<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class BulkAttributeAdminTest extends TestCase
{
    use RefreshDatabase;

    private Product $zubr60;

    private Product $titan65;

    private Product $lamp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]), 'moonshine');

        // id — как в базе (shop.battery_fitment): 15 — точная ёмкость, 20 — диапазоны.
        Attribute::query()->forceCreate(['id' => 15, 'name' => 'Емкость'])->values()->createMany([['value' => '60 Ah'], ['value' => '62 Ah']]);
        Attribute::query()->forceCreate(['id' => 20, 'name' => 'Ёмкость (Ah)'])->values()->createMany([['value' => '55 - 65 Ah'], ['value' => '66 - 77 Ah']]);
        Attribute::query()->forceCreate(['id' => 13, 'name' => 'Полярность'])->values()->create(['value' => 'Обратная']);

        $this->zubr60 = $this->product('ZUBR 60Ah О.П.', ['62 Ah', 'Обратная']);
        $this->titan65 = $this->product('TITAN 65Ah П.П.', []);
        $this->lamp = $this->product('Лампа H7', []);
    }

    public function test_name_filter_accepts_several_terms(): void
    {
        $this->get('/admin/component/product-index-page/product-resource?'.http_build_query([
            '_component_name' => 'index-table-product-resource',
            'filter' => ['name' => '60, 65'],
        ]))
            ->assertOk()
            ->assertSee('ZUBR 60Ah О.П.')
            ->assertSee('TITAN 65Ah П.П.')
            ->assertDontSee('Лампа H7');
    }

    public function test_index_has_bulk_buttons(): void
    {
        // Таблица с кнопками подгружается отдельным запросом компонента.
        $this->get('/admin/component/product-index-page/product-resource?_component_name=index-table-product-resource')
            ->assertOk()
            ->assertSee('Присвоить характеристику')
            ->assertSee('Убрать характеристику')
            ->assertSee('Емкость · 60 Ah')
            ->assertSee('Полярность · Обратная');
    }

    public function test_assign_replaces_value_and_adds_capacity_range(): void
    {
        $this->bulk('assignAttribute', [$this->zubr60->id, $this->titan65->id], ['attribute_value_id' => $this->valueId('60 Ah'), 'mode' => 'replace'])
            ->assertOk()
            ->assertJsonPath('message', 'Товаров: 2. Присвоено: Емкость — 60 Ah, 55 - 65 Ah');

        $this->assertEqualsCanonicalizing(['60 Ah', '55 - 65 Ah', 'Обратная'], $this->values($this->zubr60));
        $this->assertEqualsCanonicalizing(['60 Ah', '55 - 65 Ah'], $this->values($this->titan65));
        $this->assertSame([], $this->values($this->lamp));
    }

    public function test_assign_in_add_mode_keeps_existing_values(): void
    {
        $this->bulk('assignAttribute', [$this->zubr60->id], ['attribute_value_id' => $this->valueId('60 Ah'), 'mode' => 'add'])->assertOk();

        $this->assertEqualsCanonicalizing(['62 Ah', '60 Ah', '55 - 65 Ah', 'Обратная'], $this->values($this->zubr60));
    }

    public function test_detach_removes_all_values_of_attribute(): void
    {
        $this->bulk('detachAttribute', [$this->zubr60->id], ['attribute_id' => 15])
            ->assertOk()
            ->assertJsonPath('message', 'Товаров: 1. Убрано: Емкость');

        $this->assertSame(['Обратная'], $this->values($this->zubr60));
    }

    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $data
     */
    private function bulk(string $method, array $ids, array $data): TestResponse
    {
        return $this->postJson('/admin/method/product-index-page/product-resource?method='.$method, ['ids' => $ids, ...$data]);
    }

    /**
     * @param  list<string>  $values
     */
    private function product(string $name, array $values): Product
    {
        $product = Product::query()->create(['name' => $name, 'slug' => str($name)->slug()->value(), 'price' => 7000, 'status' => true]);
        $product->attributeValues()->attach(array_map(fn (string $value): int => $this->valueId($value), $values));

        return $product;
    }

    private function valueId(string $value): int
    {
        return AttributeValue::query()->where('value', $value)->value('id');
    }

    /**
     * @return list<string>
     */
    private function values(Product $product): array
    {
        return $product->attributeValues()->pluck('value')->all();
    }
}
