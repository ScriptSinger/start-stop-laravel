<?php

namespace Tests\Feature;

use App\Models\BrandPage;
use App\Models\Category;
use App\Models\Manufacturer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class AdminBrandPagesTest extends TestCase
{
    use RefreshDatabase;

    private const RESOURCE = '/admin/resource/brand-page-resource';

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

    public function test_brand_pages_are_in_admin_menu_and_listed(): void
    {
        BrandPage::factory()->create([
            'category_id' => Category::query()->create(['name' => 'Аккумуляторы'])->id,
            'manufacturer_id' => Manufacturer::query()->create(['name' => 'TITAN'])->id,
            'meta_title' => 'Титан в Уфе',
        ]);

        $this->get('/admin')->assertOk()->assertSee('Страницы производителей');
        $this->get(self::RESOURCE.'/brand-page-index-page')->assertOk();
        $this->get('/admin/component/brand-page-index-page/brand-page-resource?_component_name=index-table-brand-page-resource')
            ->assertOk()
            ->assertSee('Титан в Уфе')
            ->assertSee('TITAN');
    }

    public function test_form_page_opens(): void
    {
        $this->get(self::RESOURCE.'/brand-page-form-page')->assertOk()->assertSee('Meta title');
    }

    public function test_one_page_per_category_and_manufacturer_pair(): void
    {
        $category = Category::query()->create(['name' => 'Аккумуляторы']);
        $manufacturer = Manufacturer::query()->create(['name' => 'TITAN']);
        BrandPage::factory()->create(['category_id' => $category->id, 'manufacturer_id' => $manufacturer->id]);

        $this->assertThrows(
            fn () => BrandPage::factory()->create(['category_id' => $category->id, 'manufacturer_id' => $manufacturer->id]),
        );
    }
}
