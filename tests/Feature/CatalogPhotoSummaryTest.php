<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\PhotoSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogPhotoSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_splits_products_without_photo_by_activity(): void
    {
        $this->product('С фото', 'catalog/a.jpg');
        $this->product('Скрытый с фото', 'catalog/b.jpg', status: false);
        $this->product('Без поля', null);
        $this->product('Заглушка', 'no_image.png');
        $this->product('Пустая строка', '');
        $this->product('Скрытый без фото', null, status: false);
        $this->product('Удалённый без фото', null)->delete();

        $this->assertSame(
            ['with_photo' => 2, 'active_without_photo' => 3, 'inactive_without_photo' => 1],
            app(PhotoSummary::class)->overall(),
        );
    }

    public function test_summary_can_be_limited_to_categories(): void
    {
        $category = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori']);
        $this->product('В категории', null)->categories()->attach($category);
        $this->product('Вне категории', 'catalog/a.jpg');

        $this->assertSame(
            ['with_photo' => 0, 'active_without_photo' => 1, 'inactive_without_photo' => 0],
            app(PhotoSummary::class)->overall([$category->id]),
        );
    }

    private function product(string $name, ?string $image, bool $status = true): Product
    {
        return Product::query()->create(['name' => $name, 'slug' => str($name)->slug()->toString(), 'price' => 100, 'quantity' => 1, 'status' => $status, 'image' => $image]);
    }
}
