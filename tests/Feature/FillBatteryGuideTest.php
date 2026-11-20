<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Guide;
use App\Services\Catalog\BatteryGuideContent;
use App\Services\Catalog\GuideFigures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FillBatteryGuideTest extends TestCase
{
    use RefreshDatabase;

    private Attribute $technology;

    private Attribute $polarity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->technology = Attribute::query()->create(['name' => 'Технология']);
        $this->technology->values()->createMany([['value' => 'AGM'], ['value' => 'EFB'], ['value' => 'Неизвестная']]);
        $this->polarity = Attribute::query()->create(['name' => 'Полярность']);
        $this->polarity->values()->createMany([['value' => 'Обратная'], ['value' => 'Прямая']]);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('guides:fill-battery')
            ->expectsOutputToContain('Пробный прогон')
            ->assertSuccessful();

        $this->assertSame(0, Guide::query()->count());
        $this->assertNull($this->technology->fresh()->hint);
    }

    public function test_force_creates_guide_and_fills_hints(): void
    {
        $this->artisan('guides:fill-battery --force')->assertSuccessful();

        $guide = Guide::query()->where('slug', BatteryGuideContent::SLUG)->firstOrFail();
        $this->assertTrue($guide->status);
        $this->assertSame(BatteryGuideContent::META_TITLE, $guide->meta_title);
        $this->assertStringContainsString('<h2 id="polarity">', $guide->body);

        $technology = $this->technology->fresh();
        $this->assertNotNull($technology->hint);
        $this->assertSame($guide->id, $technology->guide_id);
        $this->assertSame('technology', $technology->guide_anchor);

        $this->assertNotNull($this->technology->values()->where('value', 'AGM')->first()->hint);
        $this->assertNull($this->technology->values()->where('value', 'Неизвестная')->first()->hint);
        $this->assertSame('polarity', $this->polarity->values()->where('value', 'Обратная')->first()->guide_anchor);
    }

    public function test_existing_texts_are_not_overwritten(): void
    {
        $guide = Guide::factory()->create(['slug' => BatteryGuideContent::SLUG, 'body' => 'Свой текст']);
        $this->technology->update(['hint' => 'Моя подсказка', 'guide_id' => null]);

        $this->artisan('guides:fill-battery --force')->assertSuccessful();

        $this->assertSame('Свой текст', $guide->fresh()->body);
        $this->assertSame('Моя подсказка', $this->technology->fresh()->hint);
        $this->assertSame(1, Guide::query()->count());
    }

    public function test_rewrite_body_replaces_only_guide_text(): void
    {
        $guide = Guide::factory()->create(['slug' => BatteryGuideContent::SLUG, 'body' => 'Старый текст']);

        $this->artisan('guides:fill-battery --force')->assertSuccessful();
        $this->assertSame('Старый текст', $guide->fresh()->body);

        $this->artisan('guides:fill-battery --force --rewrite-body')
            ->expectsOutputToContain('текст будет заменён')
            ->assertSuccessful();

        $this->assertSame(BatteryGuideContent::body(), $guide->fresh()->body);
        $this->assertSame(BatteryGuideContent::META_TITLE, $guide->fresh()->meta_title);
        $this->assertSame(1, Guide::query()->count());
    }

    public function test_second_run_changes_nothing(): void
    {
        $this->artisan('guides:fill-battery --force')->assertSuccessful();

        $this->artisan('guides:fill-battery')
            ->expectsOutputToContain('уже есть')
            ->assertSuccessful();
        $this->assertSame(1, Guide::query()->count());
    }

    public function test_all_figure_markers_in_the_guide_are_known_and_files_exist(): void
    {
        preg_match_all('~\[\[figure:([a-z0-9-]+)\]\]~', BatteryGuideContent::body(), $matches);

        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $name) {
            $this->assertArrayHasKey($name, GuideFigures::all(), "Нет иллюстрации {$name}");
            $this->assertFileExists(resource_path('images/guide/'.GuideFigures::all()[$name]['file']));
        }
    }

    public function test_hints_are_short_and_anchors_exist_in_guide(): void
    {
        $anchors = collect(BatteryGuideContent::attributeHints())->pluck(1)
            ->merge(collect(BatteryGuideContent::valueHints())->flatten(1)->pluck(1))
            ->unique();

        foreach ($anchors as $anchor) {
            $this->assertStringContainsString('id="'.$anchor.'"', BatteryGuideContent::body(), "Нет раздела {$anchor}");
        }

        foreach (BatteryGuideContent::attributeHints() as [$text]) {
            $this->assertLessThanOrEqual(400, mb_strlen($text));
        }
    }
}
