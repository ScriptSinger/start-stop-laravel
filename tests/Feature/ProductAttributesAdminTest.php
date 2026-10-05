<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class ProductAttributesAdminTest extends TestCase
{
    use RefreshDatabase;

    private Attribute $polarity;

    private Attribute $technology;

    private Product $product;

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

        $this->polarity = Attribute::query()->create(['name' => 'Полярность', 'sort_order' => 1]);
        $this->polarity->values()->createMany([['value' => 'Обратная'], ['value' => 'Прямая']]);

        $this->technology = Attribute::query()->create(['name' => 'Технология', 'sort_order' => 2]);
        $this->technology->values()->createMany([['value' => 'Ca'], ['value' => 'AGM']]);

        $this->product = Product::query()->create([
            'name' => 'DELKOR 100 Ah Asia О.П.',
            'slug' => 'delkor-100-ah-asia-op',
            'code' => '115D31L',
            'price' => 10150,
        ]);

        $this->product->attributeValues()->attach($this->value($this->polarity, 'Обратная'));
    }

    public function test_edit_form_shows_code_and_selected_attribute_values(): void
    {
        $response = $this->get("/admin/resource/product-resource/product-form-page/{$this->product->id}");

        $response->assertOk();
        $response->assertSee('115D31L');
        $response->assertSee('Характеристики');
        $response->assertSee('Полярность');
        $response->assertSee('Технология');
        $response->assertSee('name="attribute_'.$this->polarity->id.'[]"', false);

        $selectedId = $this->value($this->polarity, 'Обратная')->id;
        $notSelectedId = $this->value($this->polarity, 'Прямая')->id;
        $this->assertMatchesRegularExpression('/<option\s+selected\s+value="'.$selectedId.'"/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/<option\s+selected\s+value="'.$notSelectedId.'"/', $response->getContent());
    }

    public function test_saving_product_syncs_attribute_values_per_attribute(): void
    {
        $agm = $this->value($this->technology, 'AGM');
        $ca = $this->value($this->technology, 'Ca');
        $direct = $this->value($this->polarity, 'Прямая');

        $response = $this->put("/admin/resource/product-resource/crud/{$this->product->id}", [
            'name' => $this->product->name,
            'slug' => $this->product->slug,
            'code' => '115D31R',
            'price' => 10150,
            'quantity' => 0,
            'status' => 1,
            "attribute_{$this->polarity->id}" => [$direct->id],
            "attribute_{$this->technology->id}" => [$ca->id, $agm->id],
        ]);

        $response->assertRedirect();

        $this->product->refresh();

        $this->assertSame('115D31R', $this->product->code);
        $this->assertEqualsCanonicalizing(
            [$direct->id, $ca->id, $agm->id],
            $this->product->attributeValues->pluck('id')->all(),
        );
    }

    public function test_clearing_an_attribute_removes_its_values_and_keeps_others(): void
    {
        $ca = $this->value($this->technology, 'Ca');
        $this->product->attributeValues()->attach($ca);

        $this->put("/admin/resource/product-resource/crud/{$this->product->id}", [
            'name' => $this->product->name,
            'slug' => $this->product->slug,
            'price' => 10150,
            'quantity' => 0,
            'status' => 1,
            "attribute_{$this->polarity->id}" => [$this->value($this->polarity, 'Обратная')->id],
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing(
            [$this->value($this->polarity, 'Обратная')->id],
            $this->product->attributeValues()->pluck('attribute_values.id')->all(),
        );
    }

    public function test_form_shows_only_attributes_of_product_categories(): void
    {
        $viscosity = $this->scopeAttributesToCategories();

        $this->get("/admin/resource/product-resource/product-form-page/{$this->product->id}")
            ->assertOk()
            ->assertSee('name="attribute_'.$this->polarity->id.'[]"', false)
            ->assertDontSee('name="attribute_'.$viscosity->id.'[]"', false);
    }

    public function test_form_keeps_foreign_attribute_visible_when_product_has_its_value(): void
    {
        $viscosity = $this->scopeAttributesToCategories();
        $this->product->attributeValues()->attach($this->value($viscosity, '5W-30'));

        $this->get("/admin/resource/product-resource/product-form-page/{$this->product->id}")
            ->assertOk()
            ->assertSee('name="attribute_'.$viscosity->id.'[]"', false);
    }

    /**
     * Полярность и технология — у аккумуляторов, вязкость — у масел;
     * товар из setUp кладём в «Аккумуляторы».
     */
    private function scopeAttributesToCategories(): Attribute
    {
        $batteries = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori']);
        $oils = Category::query()->create(['name' => 'Автомасла', 'slug' => 'avtomasla']);

        $this->polarity->categories()->attach($batteries);
        $this->technology->categories()->attach($batteries);

        $viscosity = Attribute::query()->create(['name' => 'Вязкость']);
        $viscosity->values()->create(['value' => '5W-30']);
        $viscosity->categories()->attach($oils);

        $this->product->categories()->attach($batteries);

        return $viscosity;
    }

    private function value(Attribute $attribute, string $value): AttributeValue
    {
        return $attribute->values()->where('value', $value)->firstOrFail();
    }
}
