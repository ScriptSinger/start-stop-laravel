<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateSitemapTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/sitemap.xml');
        @mkdir(dirname($this->path), 0777, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    public function test_contains_only_live_pages(): void
    {
        $tools = Category::query()->create(['name' => 'Автотовары', 'slug' => 'avtotovari', 'status' => true]);
        $compressors = Category::query()->create(['name' => 'Компрессоры', 'slug' => 'kompressori', 'parent_id' => $tools->id, 'status' => true]);
        $empty = Category::query()->create(['name' => 'Пустой', 'slug' => 'pustoy', 'status' => true]);
        $onlyArchive = Category::query()->create(['name' => 'Только архив', 'slug' => 'tolko-arkhiv', 'status' => true]);

        $compressors->products()->attach(Product::query()->create(['name' => 'Компрессор', 'slug' => 'kompressor', 'price' => 1, 'status' => true]));
        $onlyArchive->products()->attach(Product::query()->create(['name' => 'Архивный', 'slug' => 'arkhivnyy', 'price' => 1, 'status' => false]));
        Page::query()->create(['title' => 'О компании', 'slug' => 'about_us', 'status' => true]);
        Page::query()->create(['title' => 'Черновик', 'slug' => 'chernovik', 'status' => false]);

        $this->artisan('sitemap:generate', ['--path' => $this->path])->assertSuccessful();

        $xml = file_get_contents($this->path);

        $this->assertStringContainsString('<loc>'.route('product.show', 'kompressor').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('category.show', $compressors).'</loc>', $xml);
        // Родитель раздела с товарами тоже не пустой.
        $this->assertStringContainsString('<loc>'.route('category.show', $tools).'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('page.show', 'about_us').'</loc>', $xml);

        $this->assertStringNotContainsString('arkhivnyy', $xml);
        $this->assertStringNotContainsString($empty->slug, $xml);
        $this->assertStringNotContainsString($onlyArchive->slug, $xml);
        $this->assertStringNotContainsString('chernovik', $xml);
    }

    public function test_rebuilt_every_night(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'sitemap:generate'));

        $this->assertNotNull($event);
        $this->assertSame('15 3 * * *', $event->expression);
        $this->assertSame('Asia/Yekaterinburg', $event->timezone);
    }
}
