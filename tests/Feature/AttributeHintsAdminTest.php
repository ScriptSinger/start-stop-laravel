<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Guide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class AttributeHintsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]), 'moonshine');
    }

    public function test_guides_section_is_available_in_admin(): void
    {
        Guide::factory()->create(['title' => 'Как выбрать аккумулятор', 'slug' => 'akb']);

        $this->get('/admin/resource/guide-resource/guide-index-page')->assertOk()->assertSee('Гайды');

        $this->get('/admin/component/guide-index-page/guide-resource?'.http_build_query([
            '_component_name' => 'index-table-guide-resource',
        ]))->assertOk()->assertSee('Как выбрать аккумулятор');
    }

    public function test_attribute_form_has_hint_and_guide_fields(): void
    {
        $attribute = Attribute::query()->create(['name' => 'Полярность']);

        $this->get("/admin/resource/attribute-resource/attribute-form-page/{$attribute->id}")
            ->assertOk()
            ->assertSee('name="hint"', false)
            ->assertSee('name="guide_id"', false)
            ->assertSee('name="guide_anchor"', false);
    }

    public function test_attribute_hint_and_guide_are_saved(): void
    {
        $guide = Guide::factory()->create(['slug' => 'akb']);
        $attribute = Attribute::query()->create(['name' => 'Полярность']);

        $this->put("/admin/resource/attribute-resource/crud/{$attribute->id}", [
            'name' => 'Полярность',
            'hint' => 'Расположение плюсовой клеммы.',
            'guide_id' => $guide->id,
            'guide_anchor' => 'polarity',
            'sort_order' => 0,
        ])->assertRedirect();

        $attribute->refresh();
        $this->assertSame('Расположение плюсовой клеммы.', $attribute->hint);
        $this->assertSame($guide->id, $attribute->guide->id);
        $this->assertSame('polarity', $attribute->guide_anchor);
    }

    public function test_value_hint_is_saved_through_the_attribute_form(): void
    {
        $attribute = Attribute::query()->create(['name' => 'Технология']);
        $efb = $attribute->values()->create(['value' => 'EFB']);

        $this->put("/admin/resource/attribute-resource/crud/{$attribute->id}", [
            'name' => 'Технология',
            'sort_order' => 0,
            'values' => [
                ['id' => $efb->id, 'value' => 'EFB', 'hint' => 'Для Start-Stop.', 'guide_anchor' => 'efb', 'sort_order' => 0],
            ],
        ])->assertRedirect();

        $efb->refresh();
        $this->assertSame('Для Start-Stop.', $efb->hint);
        $this->assertSame('efb', $efb->guide_anchor);
    }

    public function test_deleting_guide_keeps_attribute(): void
    {
        $guide = Guide::factory()->create();
        $attribute = Attribute::query()->create(['name' => 'Полярность', 'guide_id' => $guide->id]);

        $guide->delete();

        $this->assertNull($attribute->fresh()->guide_id);
    }
}
