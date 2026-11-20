<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class AttributeCardVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $exact = Attribute::query()->create(['name' => 'Емкость']);
        $range = Attribute::query()->create(['name' => 'Ёмкость (Ah)', 'show_on_card' => false]);

        $this->product = Product::query()->create(['name' => 'TITAN 60', 'slug' => 'titan-60', 'price' => 7000, 'status' => true]);
        $this->product->attributeValues()->attach([
            $exact->values()->create(['value' => '60 Ah'])->id,
            $range->values()->create(['value' => '55 - 65 Ah'])->id,
        ]);
        $this->product->categories()->attach(Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akb', 'status' => true]));
    }

    public function test_attributes_are_shown_on_card_by_default(): void
    {
        $this->assertTrue(Attribute::query()->create(['name' => 'Новая'])->fresh()->show_on_card);
    }

    public function test_missing_column_before_migration_shows_everything_instead_of_failing(): void
    {
        $this->product->load('attributeValues.attribute');
        $this->product->attributeValues->each(fn ($value) => $value->attribute->offsetUnset('show_on_card'));

        $this->assertSame(2, $this->product->specifications(visibleOnly: true)->count());
    }

    public function test_hidden_attribute_is_not_on_product_page_but_others_are(): void
    {
        $this->get(route('product.show', $this->product))
            ->assertOk()
            ->assertSee('60 Ah')
            ->assertDontSee('55 - 65 Ah')
            ->assertDontSee('Ёмкость (Ah)');
    }

    public function test_hidden_attribute_is_not_in_listing_card(): void
    {
        $this->get(route('category.show', Category::query()->firstOrFail()))
            ->assertOk()
            ->assertSee('TITAN 60')
            ->assertDontSee('55 - 65 Ah');
    }

    public function test_limit_applies_after_hidden_attributes_are_skipped(): void
    {
        $first = Attribute::query()->create(['name' => 'Служебная', 'show_on_card' => false, 'display_sort_order' => 0]);
        $this->product->attributeValues()->attach($first->values()->create(['value' => 'скрыто'])->id);
        $this->product->load('attributeValues.attribute');

        $this->assertSame(['Емкость'], $this->product->specifications(1, visibleOnly: true)->keys()->all());
        $this->assertSame(3, $this->product->specifications()->count());
    }

    public function test_compare_page_skips_hidden_attribute(): void
    {
        $this->post(route('compare.store', $this->product));

        $this->get(route('compare.index'))
            ->assertOk()
            ->assertSee('60 Ah')
            ->assertDontSee('55 - 65 Ah');
    }

    public function test_toggles_are_switched_right_in_the_attribute_list(): void
    {
        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]), 'moonshine');
        $attribute = Attribute::query()->where('name', 'Емкость')->firstOrFail();

        $this->put("/admin/update-field/column/attribute-resource/{$attribute->id}", ['field' => 'show_on_card', 'value' => 0])->assertSuccessful();
        $this->assertFalse($attribute->fresh()->show_on_card);

        $this->put("/admin/update-field/column/attribute-resource/{$attribute->id}", ['field' => 'is_filterable', 'value' => 0])->assertSuccessful();
        $this->assertFalse($attribute->fresh()->is_filterable);
        $this->assertFalse($attribute->fresh()->show_on_card);

        $this->get('/admin/component/attribute-index-page/attribute-resource?'.http_build_query(['_component_name' => 'index-table-attribute-resource']))
            ->assertOk()
            ->assertSee('На карточке')
            ->assertSee('update-field/column/attribute-resource', false);
    }

    public function test_admin_still_sees_all_attributes_and_can_toggle(): void
    {
        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]), 'moonshine');

        $this->get("/admin/resource/product-resource/product-detail-page/{$this->product->id}")
            ->assertOk()
            ->assertSee('55 - 65 Ah');

        $range = Attribute::query()->where('name', 'Ёмкость (Ah)')->firstOrFail();
        $this->get("/admin/resource/attribute-resource/attribute-form-page/{$range->id}")
            ->assertOk()
            ->assertSee('name="show_on_card"', false);

        $this->put("/admin/resource/attribute-resource/crud/{$range->id}", [
            'name' => 'Ёмкость (Ah)',
            'sort_order' => 0,
            'show_on_card' => 1,
        ])->assertRedirect();

        $this->assertTrue($range->fresh()->show_on_card);
    }
}
