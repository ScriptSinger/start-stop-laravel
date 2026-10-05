<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HtmlDescriptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{?string, bool}>
     */
    public static function descriptions(): array
    {
        return [
            'пусто' => [null, false],
            'пустой абзац из OpenCart' => ['<p><br></p>', false],
            'неразрывные пробелы' => ["<p>\u{00A0} </p>", false],
            'текст' => ['<p>Пусковой ток 540 A</p>', true],
            'только картинка' => ['<p><img src="/image/a.jpg"></p>', true],
        ];
    }

    #[DataProvider('descriptions')]
    public function test_has_description(?string $description, bool $expected): void
    {
        $this->assertSame($expected, (new Product(['description' => $description]))->hasDescription());
    }

    public function test_category_page_hides_empty_markup_and_renders_real_html(): void
    {
        $empty = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori', 'description' => '<p><br></p>']);
        $filled = Category::query()->create(['name' => 'Клеммы', 'slug' => 'klemmy', 'description' => '<p>Латунные <b>клеммы</b></p>']);

        $this->get(route('category.show', $empty))
            ->assertOk()
            ->assertDontSee('category-info__description', false)
            ->assertDontSee('&lt;p&gt;', false);

        $this->get(route('category.show', $filled))
            ->assertOk()
            ->assertSee('<p>Латунные <b>клеммы</b></p>', false);
    }

    public function test_product_name_with_quotes_is_escaped_once(): void
    {
        $product = Product::query()->create(['name' => 'Клемма "-" Start Volt', 'slug' => 'klemma', 'price' => 500]);

        $this->get(route('product.show', $product))
            ->assertOk()
            ->assertSee('<h1>Клемма &quot;-&quot; Start Volt</h1>', false)
            ->assertDontSee('&amp;quot;', false);
    }
}
