<?php

namespace Tests\Feature;

use App\Enums\MenuLocation;
use App\Models\BatteryFitment;
use App\Models\Category;
use App\Models\Guide;
use App\Models\MenuItem;
use App\Models\Product;
use App\Services\Catalog\BatteryGuideContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuideSeoTest extends TestCase
{
    use RefreshDatabase;

    private Guide $guide;

    private Category $batteries;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guide = Guide::factory()->create([
            'slug' => BatteryGuideContent::SLUG,
            'title' => 'Как выбрать аккумулятор',
            'meta_title' => 'Как выбрать аккумулятор: полярность и EFB | Старт-Стоп',
        ]);
        $this->batteries = Category::query()->forceCreate(['id' => 1, 'name' => 'Аккумуляторы', 'slug' => 'akkumulyatory', 'status' => true]);
    }

    public function test_head_has_title_article_markup_and_breadcrumbs(): void
    {
        $page = $this->get(route('guide.show', $this->guide))->assertOk();

        $page->assertSee('<title>Как выбрать аккумулятор: полярность и EFB | Старт-Стоп</title>', false)
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('article:modified_time', false)
            ->assertSee('"@type":"Article"', false)
            ->assertSee('"dateModified"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('<link rel="canonical" href="'.route('guide.show', $this->guide).'">', false);
    }

    public function test_table_of_contents_links_scroll_smoothly_with_plain_anchor_fallback(): void
    {
        $this->guide->update(['body' => '<h2 id="technology">Технология</h2><p>Текст.</p>']);

        $this->get(route('guide.show', $this->guide))
            ->assertOk()
            ->assertSee('<a href="#technology" @click.prevent="go(\'technology\')"', false);
    }

    public function test_body_markers_become_internal_links(): void
    {
        BatteryFitment::query()->create(['brand' => 'Kia', 'model' => 'Rio', 'capacity' => '60 Ач', 'polarity' => 'Обратная']);
        $this->guide->update(['body' => '<p>Смотрите [[category:1|аккумуляторы]], [[cars|подбор по авто]] и [[brand:kia|Kia]].</p>']);

        $this->get(route('guide.show', $this->guide))
            ->assertOk()
            ->assertSee('<a href="'.route('category.show', $this->batteries).'">аккумуляторы</a>', false)
            ->assertSee('<a href="'.route('car-landing.index').'">подбор по авто</a>', false)
            ->assertSee('<a href="'.route('car-landing.brand', 'kia').'">Kia</a>', false)
            ->assertDontSee('[[', false);
    }

    public function test_links_to_missing_targets_stay_plain_text(): void
    {
        $this->batteries->update(['status' => false]);
        $this->guide->update(['body' => '<p>[[category:1|скрытый раздел]] и [[category:999|нет такого]] и [[brand:nope|марка]].</p>']);

        $this->get(route('guide.show', $this->guide))
            ->assertOk()
            ->assertSee('скрытый раздел и нет такого и марка.', false)
            ->assertDontSee(route('category.show', $this->batteries), false);
    }

    public function test_catalog_block_lists_battery_categories_and_popular_brands(): void
    {
        BatteryFitment::query()->create(['brand' => 'Kia', 'model' => 'Rio', 'capacity' => '60 Ач', 'polarity' => 'Обратная']);
        $this->guide->update(['body' => '<h2 id="catalog">Каталог</h2><p>[[block:catalog]]</p>']);

        $this->get(route('guide.show', $this->guide))
            ->assertOk()
            ->assertSee('guide-catalog__item_main', false)
            ->assertSee(route('category.show', $this->batteries), false)
            ->assertSee('Аккумуляторы для Kia')
            ->assertSee(route('car-landing.index'), false)
            ->assertDontSee('<p><div class="guide-catalog">', false);
    }

    public function test_battery_category_and_product_link_to_the_guide(): void
    {
        $product = Product::query()->create(['name' => 'TITAN EFB 70', 'slug' => 'titan-efb-70', 'price' => 9000, 'status' => true]);
        $product->categories()->attach($this->batteries);

        $link = '<a href="'.route('guide.show', $this->guide).'"';

        $this->get(route('category.show', $this->batteries))->assertOk()->assertSee($link, false);
        $this->get(route('product.show', $product))->assertOk()->assertSee($link, false)->assertSee('Не уверены, подойдёт ли аккумулятор?');
    }

    public function test_other_categories_and_products_have_no_guide_link(): void
    {
        $tools = Category::query()->forceCreate(['id' => 500, 'name' => 'Инструмент', 'slug' => 'instrument', 'status' => true]);
        $product = Product::query()->create(['name' => 'Ключ', 'slug' => 'klyuch', 'price' => 100, 'status' => true]);
        $product->categories()->attach($tools);

        $this->get(route('category.show', $tools))->assertOk()->assertDontSee(route('guide.show', $this->guide), false);
        $this->get(route('product.show', $product))->assertOk()->assertDontSee('Не уверены, подойдёт ли аккумулятор?');
    }

    public function test_hidden_guide_is_not_linked_from_catalog(): void
    {
        $this->guide->update(['status' => false]);

        $this->get(route('category.show', $this->batteries))->assertOk()->assertDontSee(route('guide.show', $this->guide), false);
    }

    public function test_footer_links_only_to_active_guides(): void
    {
        $hidden = Guide::factory()->create(['slug' => 'hidden-guide', 'title' => 'Скрытый гайд', 'status' => false]);
        MenuItem::query()->create(['location' => MenuLocation::Footer, 'title' => 'Информация']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<a href="'.route('guide.show', $this->guide).'"', false)
            ->assertDontSee(route('guide.show', $hidden), false);
    }

    public function test_description_is_built_from_text_with_spaces_between_blocks(): void
    {
        $this->guide->update(['meta_description' => null, 'body' => '<h2 id="a">Полярность</h2><p>Текст раздела.</p>[[figure:date]]']);

        $this->get(route('guide.show', $this->guide))
            ->assertOk()
            ->assertSee('<meta name="description" content="Полярность Текст раздела.">', false);
    }

    public function test_robots_txt_closes_hint_pages(): void
    {
        $this->assertStringContainsString("Disallow: /hint/\n", file_get_contents(public_path('robots.txt')));
    }

    public function test_default_guide_content_has_seo_texts(): void
    {
        $this->assertLessThanOrEqual(75, mb_strlen(BatteryGuideContent::META_TITLE));
        $this->assertLessThanOrEqual(200, mb_strlen(BatteryGuideContent::META_DESCRIPTION));
        $this->assertGreaterThanOrEqual(600, str_word_count(strip_tags(BatteryGuideContent::body()), 0, 'абвгдеёжзийклмнопрстуфхцчшщъыьэюяАБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ'));
        $this->assertSame(1, preg_match_all('~<h2 id="catalog">~', BatteryGuideContent::body()));
    }
}
