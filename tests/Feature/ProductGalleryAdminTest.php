<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class ProductGalleryAdminTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private ProductImage $existing;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $admin = MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($admin, 'moonshine');

        $this->product = Product::query()->create([
            'name' => 'TITAN 60Ah О.П.',
            'slug' => 'titan-60ah-op',
            'price' => 5200,
            'image' => 'catalog/akb/titan.jpg',
        ]);

        $this->existing = $this->product->images()->create(['path' => 'catalog/productimg/extra.jpg', 'sort_order' => 1]);
    }

    public function test_photo_tab_shows_main_and_extra_photos(): void
    {
        $this->get("/admin/resource/product-resource/product-form-page/{$this->product->id}")
            ->assertOk()
            ->assertSee('Дополнительные фото')
            // Превью — по пути из базы: импортированные фото лежат в разных
            // папках, к ним нельзя приписывать папку новых загрузок.
            ->assertSee('src="'.Storage::disk('public')->url('catalog/akb/titan.jpg').'"', false)
            ->assertSee('src="'.Storage::disk('public')->url('catalog/productimg/extra.jpg').'"', false)
            ->assertDontSee('catalog/products/catalog/', false);
    }

    public function test_saving_keeps_existing_photos_and_adds_uploaded_one(): void
    {
        $this->saveProduct([
            ['id' => $this->existing->id, 'hidden_path' => 'catalog/productimg/extra.jpg', 'sort_order' => 2],
            ['path' => UploadedFile::fake()->image('new.jpg'), 'sort_order' => 1],
        ])->assertRedirect();

        $this->assertSame('catalog/akb/titan.jpg', $this->product->refresh()->image);

        $images = $this->product->images()->get();
        $this->assertCount(2, $images);

        $uploaded = $images->firstWhere('id', '!=', $this->existing->id);
        $this->assertStringStartsWith('catalog/products/', $uploaded->path);
        Storage::disk('public')->assertExists($uploaded->path);

        // Порядок: новое фото (1) раньше старого (2).
        $this->assertSame($uploaded->id, $images->first()->id);
    }

    public function test_removed_row_deletes_record_but_keeps_shared_file(): void
    {
        Storage::disk('public')->put('catalog/productimg/extra.jpg', 'jpeg');

        $this->saveProduct([])->assertRedirect();

        $this->assertSame(0, $this->product->images()->count());
        // Файл может использоваться другими товарами — с диска не удаляем.
        Storage::disk('public')->assertExists('catalog/productimg/extra.jpg');
    }

    /**
     * @param  list<array<string, mixed>>  $images
     */
    private function saveProduct(array $images): TestResponse
    {
        // Как браузер: multipart POST с подменой метода — так форма MoonShine
        // и отправляет файлы.
        return $this->post("/admin/resource/product-resource/crud/{$this->product->id}", [
            '_method' => 'PUT',
            'name' => $this->product->name,
            'slug' => $this->product->slug,
            'price' => 5200,
            'quantity' => 0,
            'status' => 1,
            'hidden_image' => 'catalog/akb/titan.jpg',
            'images' => $images,
        ]);
    }
}
