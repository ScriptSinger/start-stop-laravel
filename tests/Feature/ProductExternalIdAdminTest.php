<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Rap2hpoutre\FastExcel\FastExcel;
use Tests\TestCase;

class ProductExternalIdAdminTest extends TestCase
{
    use RefreshDatabase;

    private Product $linked;

    private Product $unlinked;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]), 'moonshine');

        $this->linked = Product::query()->create(['name' => 'AKOM 75 Ah О.П.', 'slug' => 'akom-75', 'price' => 9800, 'external_id' => 'РТ-00000789']);
        $this->unlinked = Product::query()->create(['name' => 'ZUBR 60 Ah О.П.', 'slug' => 'zubr-60', 'price' => 7600]);
    }

    public function test_index_shows_external_id_column_and_value(): void
    {
        $this->productTable([])
            ->assertSee('Код 1С')
            ->assertSee('РТ-00000789');
    }

    public function test_filter_by_external_id_accepts_several_codes(): void
    {
        $this->productTable(['filter' => ['external_id' => 'РТ-00000789']])
            ->assertSee('AKOM 75 Ah О.П.')
            ->assertDontSee('ZUBR 60 Ah О.П.');

        $this->productTable(['filter' => ['external_id' => 'РТ-00000789, 999']])
            ->assertSee('AKOM 75 Ah О.П.');
    }

    public function test_filter_without_external_id(): void
    {
        $this->productTable(['filter' => ['without_external_id' => '1']])
            ->assertSee('ZUBR 60 Ah О.П.')
            ->assertDontSee('AKOM 75 Ah О.П.');
    }

    public function test_search_finds_product_by_external_id(): void
    {
        $this->productTable(['search' => 'РТ-00000789'])
            ->assertSee('AKOM 75 Ah О.П.')
            ->assertDontSee('ZUBR 60 Ah О.П.');
    }

    public function test_form_and_detail_page_show_external_id(): void
    {
        $this->get("/admin/resource/product-resource/product-form-page/{$this->linked->id}")
            ->assertOk()
            ->assertSee('name="external_id"', false)
            ->assertSee('РТ-00000789');

        $this->get("/admin/resource/product-resource/product-detail-page/{$this->linked->id}")
            ->assertOk()
            ->assertSee('Код 1С')
            ->assertSee('РТ-00000789');
    }

    public function test_form_saves_external_id(): void
    {
        $this->save($this->unlinked, ['external_id' => ' РТ-00000100 '])->assertRedirect();

        $this->assertSame('РТ-00000100', $this->unlinked->refresh()->external_id);
    }

    public function test_form_clears_external_id_when_left_empty(): void
    {
        $this->save($this->linked, ['external_id' => ''])->assertRedirect();

        $this->assertNull($this->linked->refresh()->external_id);
    }

    public function test_form_rejects_duplicate_external_id(): void
    {
        $this->save($this->unlinked, ['external_id' => 'РТ-00000789'])->assertSessionHasErrors('external_id', null, 'product-resource');

        $this->assertNull($this->unlinked->refresh()->external_id);
    }

    public function test_form_keeps_own_external_id_on_resave(): void
    {
        $this->save($this->linked, ['external_id' => 'РТ-00000789'])->assertRedirect();

        $this->assertSame('РТ-00000789', $this->linked->refresh()->external_id);
    }

    public function test_export_contains_external_id_column(): void
    {
        $this->assertTrue(collect($this->exportHeaders())->contains('Код 1С'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(Product $product, array $data): TestResponse
    {
        return $this->put("/admin/resource/product-resource/crud/{$product->id}", [
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->price,
            'quantity' => 0,
            'status' => 1,
            ...$data,
        ]);
    }

    /**
     * @return list<string>
     */
    private function exportHeaders(): array
    {
        $response = $this->get('/admin/resource/product-resource/handler/export-handler')->assertOk();
        $rows = (new FastExcel)->import($response->baseResponse->getFile()->getPathname());

        return array_keys($rows->first());
    }

    private function productTable(array $query): TestResponse
    {
        return $this->get('/admin/component/product-index-page/product-resource?'.http_build_query([
            '_component_name' => 'index-table-product-resource',
            ...$query,
        ]))->assertOk();
    }
}
