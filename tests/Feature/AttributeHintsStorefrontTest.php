<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Guide;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributeHintsStorefrontTest extends TestCase
{
    use RefreshDatabase;

    private Guide $guide;

    private Attribute $technology;

    private Attribute $voltage;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guide = Guide::factory()->create(['title' => 'Как выбрать аккумулятор', 'slug' => 'akb']);
        $this->technology = Attribute::query()->create([
            'name' => 'Технология',
            'hint' => 'Как устроена батарея.',
            'guide_id' => $this->guide->id,
            'guide_anchor' => 'technology',
        ]);
        $efb = $this->technology->values()->create(['value' => 'EFB', 'hint' => 'Усиленная технология для Start-Stop.', 'guide_anchor' => 'efb']);
        $this->technology->values()->create(['value' => 'Calcium']);
        $this->voltage = Attribute::query()->create(['name' => 'Напряжение']);
        $twelve = $this->voltage->values()->create(['value' => '12V']);

        $this->product = Product::query()->create(['name' => 'TITAN EFB 70', 'slug' => 'titan-efb-70', 'price' => 9000, 'status' => true]);
        $this->product->attributeValues()->attach([$efb->id, $twelve->id]);
    }

    public function test_product_page_shows_hint_trigger_only_for_attributes_with_hint(): void
    {
        $page = $this->get(route('product.show', $this->product))->assertOk();

        $page->assertSee(route('hint.attribute', $this->technology), false)
            ->assertSee('Что такое «Технология»', false)
            ->assertDontSee(route('hint.attribute', $this->voltage), false);
    }

    public function test_product_page_links_values_that_have_a_hint(): void
    {
        $efb = $this->technology->values()->where('value', 'EFB')->firstOrFail();

        $this->get(route('product.show', $this->product))
            ->assertOk()
            ->assertSee('class="spec-hint-term"', false)
            ->assertSee(route('hint.value', $efb), false);
    }

    public function test_attribute_hint_fragment_links_to_guide_section(): void
    {
        $this->get(route('hint.attribute', $this->technology), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('Как устроена батарея.')
            ->assertSee('Больше советов по выбору')
            ->assertSee(route('guide.show', $this->guide).'#technology', false)
            ->assertSee('target="_blank" rel="noopener"', false)
            ->assertDontSee('<html', false);
    }

    public function test_value_hint_uses_its_own_anchor_and_falls_back_to_attribute_anchor(): void
    {
        $efb = $this->technology->values()->where('value', 'EFB')->firstOrFail();
        $this->get(route('hint.value', $efb), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee(route('guide.show', $this->guide).'#efb', false);

        $efb->update(['guide_anchor' => null]);
        $this->get(route('hint.value', $efb), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertSee(route('guide.show', $this->guide).'#technology', false);
    }

    public function test_hint_without_guide_has_no_more_link(): void
    {
        $this->voltage->update(['hint' => 'Номинальное напряжение сети.']);

        $this->get(route('hint.attribute', $this->voltage), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('Номинальное напряжение сети.')
            ->assertDontSee('Больше советов по выбору');
    }

    public function test_hint_page_works_without_javascript(): void
    {
        $this->get(route('hint.attribute', $this->technology))
            ->assertOk()
            ->assertSee('<html', false)
            ->assertSee('Как устроена батарея.')
            ->assertSee('noindex', false);
    }

    public function test_attribute_without_hint_is_not_found(): void
    {
        $this->get(route('hint.attribute', $this->voltage))->assertNotFound();
        $this->get(route('hint.value', $this->voltage->values()->firstOrFail()))->assertNotFound();
    }

    public function test_hint_text_is_escaped(): void
    {
        $this->voltage->update(['hint' => '<script>alert(1)</script>']);

        $this->get(route('hint.attribute', $this->voltage), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_inactive_guide_is_hidden_and_not_linked(): void
    {
        $this->guide->update(['status' => false]);

        $this->get(route('guide.show', $this->guide))->assertNotFound();
        $this->get(route('hint.attribute', $this->technology), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertDontSee('Больше советов по выбору');
    }

    public function test_guide_page_has_table_of_contents_and_help_blocks(): void
    {
        $this->guide->update(['body' => '<p>Вступление.</p><h2 id="technology">Технология</h2><p>Текст.</p><h2 id="polarity">Полярность <em>АКБ</em></h2><p>Текст.</p>']);

        $this->get(route('guide.show', $this->guide))
            ->assertOk()
            ->assertSee('aria-label="Содержание"', false)
            ->assertSee('href="#technology"', false)
            ->assertSee('href="#polarity"', false)
            ->assertSee('мин чтения')
            ->assertSee('Подобрать по автомобилю')
            ->assertSee(route('car-landing.index'), false)
            ->assertSee(config('shop.phone'));
    }

    public function test_guide_figures_are_rendered_from_markers(): void
    {
        $this->guide->update(['body' => '<h2 id="terminals">Клеммы</h2><p>[[figure:terminals]]</p>[[figure:date]]<p>[[figure:unknown]]</p>']);

        $page = $this->get(route('guide.show', $this->guide))->assertOk();

        $page->assertSee('<figure class="guide-figure">', false)
            ->assertSee('Основные типы клемм')
            ->assertSee('Где искать дату выпуска')
            ->assertSee('alt="Схема: отметка с датой выпуска на корпусе аккумулятора"', false)
            ->assertSee('loading="lazy"', false)
            ->assertDontSee('[[figure:', false)
            ->assertDontSee('<p><figure', false);
    }

    public function test_guide_sections_and_reading_time(): void
    {
        $guide = new Guide(['body' => '<h2 id="a">Первый &amp; второй</h2><p>текст</p><h2 class="x" id="b">Второй</h2><h2>Без якоря</h2>']);

        $this->assertSame([
            ['id' => 'a', 'title' => 'Первый & второй'],
            ['id' => 'b', 'title' => 'Второй'],
        ], $guide->sections());
        $this->assertSame(1, $guide->readingMinutes());
        $this->assertSame(2, (new Guide(['body' => str_repeat('слово ', 200)]))->readingMinutes());
        $this->assertSame([], (new Guide(['body' => null]))->sections());
    }

    public function test_guide_page_without_sections_has_no_table_of_contents(): void
    {
        $this->guide->update(['body' => '<p>Только текст.</p>']);

        $this->get(route('guide.show', $this->guide))
            ->assertOk()
            ->assertDontSee('aria-label="Содержание"', false);
    }

    public function test_guide_page_shows_body_with_section_anchors(): void
    {
        $this->get(route('guide.show', $this->guide))
            ->assertOk()
            ->assertSee('Как выбрать аккумулятор')
            ->assertSee('<h2 id="polarity">', false);
    }
}
