<?php

namespace Tests\Feature;

use App\Enums\CustomerRequestType;
use App\Models\Category;
use App\Models\CustomerRequest;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPageTest extends TestCase
{
    use RefreshDatabase;

    private Category $batteries;

    protected function setUp(): void
    {
        parent::setUp();

        $this->batteries = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori']);
    }

    public function test_heading_seo_and_breadcrumbs_with_manufacturer(): void
    {
        $tyumen = Manufacturer::query()->create(['name' => 'ТЮМЕНЬ', 'slug' => 'tyumen']);
        $product = $this->product(10, 'ТЮМЕНЬ ASIA 40 Ah П.П.', [
            'manufacturer_id' => $tyumen->id,
            'heading' => 'Аккумулятор ТЮМЕНЬ ASIA 40 Ah П.П.',
            'meta_title' => 'Аккумулятор ТЮМЕНЬ ASIA в Уфе',
            'meta_description' => 'Купить АКБ',
        ]);

        $this->get(route('product.show', $product))
            ->assertOk()
            ->assertSee('<h1>Аккумулятор ТЮМЕНЬ ASIA 40 Ah П.П.</h1>', false)
            ->assertSee('<title>Аккумулятор ТЮМЕНЬ ASIA в Уфе</title>', false)
            ->assertSee('<meta name="description" content="Купить АКБ">', false)
            ->assertSee(e(route('category.show', ['category' => $this->batteries, 'manufacturer' => [$tyumen->id]])), false);
    }

    public function test_structured_data_has_price_availability_and_breadcrumbs(): void
    {
        $product = $this->product(10, 'ZUBR 60 Ah', ['image' => 'catalog/zubr.jpg', 'sku' => '306']);

        $html = $this->get(route('product.show', $product))
            ->assertOk()
            ->assertSee('<meta property="og:type" content="product">', false)
            ->assertSee('<meta property="og:image" content="'.url('/storage/catalog/zubr.jpg').'">', false)
            ->assertSee('<link rel="canonical" href="'.route('product.show', $product).'">', false)
            ->getContent();

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        [$productData, $breadcrumbs] = array_map(fn (string $json): array => json_decode($json, true), $matches[1]);

        $this->assertSame('Product', $productData['@type']);
        $this->assertSame('306', $productData['sku']);
        $this->assertSame(['@type' => 'Offer', 'url' => route('product.show', $product), 'price' => '7000.00', 'priceCurrency' => 'RUB', 'availability' => 'https://schema.org/InStock'], $productData['offers']);
        $this->assertSame('BreadcrumbList', $breadcrumbs['@type']);
        $this->assertSame(['Старт-Стоп', 'Аккумуляторы', 'ZUBR 60 Ah'], array_column($breadcrumbs['itemListElement'], 'name'));
    }

    public function test_inactive_product_page_is_not_found(): void
    {
        $product = $this->product(10, 'Архивный', ['status' => false]);

        $this->get(route('product.show', $product))->assertNotFound();
    }

    public function test_similar_products_follow_theme_algorithm(): void
    {
        $current = $this->product(10, 'Текущий');
        $this->product(5, 'Меньший id');
        $this->product(11, 'Следующий 11');
        $this->product(12, 'Следующий 12');
        $this->product(13, 'Нет в наличии', ['quantity' => 0]);
        $this->product(14, 'Неактивный', ['status' => false]);
        $this->product(15, 'Другая категория', category: false);

        // Хватает товаров с большим id? Нет (только 11 и 12 при лимите 5) —
        // берутся все из категории по возрастанию id: 5, 11, 12.
        $this->get(route('product.show', $current))
            ->assertOk()
            ->assertSee('Похожие товары')
            ->assertSeeInOrder(['Меньший id', 'Следующий 11', 'Следующий 12'])
            ->assertDontSee('Нет в наличии</a>', false)
            ->assertDontSee('Неактивный')
            ->assertDontSee('Другая категория');
    }

    public function test_product_question_creates_request_linked_to_product(): void
    {
        $product = $this->product(10, 'TITAN 60');

        $this->get(route('product.show', $product))->assertSee('Вопрос-ответ')->assertSee('Задать вопрос');

        $this->postJson(route('product-question.store', $product), ['name' => 'Олег', 'phone' => '+7 (999) 000-00-00', 'comment' => 'Подойдёт на Kia Rio?'])
            ->assertOk();

        $request = CustomerRequest::query()->sole();
        $this->assertSame(CustomerRequestType::ProductQuestion, $request->type);
        $this->assertSame($product->id, $request->product_id);

        $this->postJson(route('product-question.store', $product), ['name' => 'Олег', 'phone' => '+7 (999) 000-00-00'])
            ->assertJsonValidationErrors(['comment' => 'Напишите вопрос.']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(int $id, string $name, array $attributes = [], bool $category = true): Product
    {
        $product = new Product([
            'name' => $name,
            'slug' => 'p-'.$id,
            'price' => 7000,
            'quantity' => 2,
            'status' => true,
            ...$attributes,
        ]);
        $product->id = $id;
        $product->save();

        if ($category) {
            $product->categories()->attach($this->batteries);
        }

        return $product;
    }
}
