<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Guide;
use App\Models\Manufacturer;
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
        array_map('unlink', glob(dirname($this->path).'/sitemap*'));

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

    public function test_writes_one_file_with_all_page_types(): void
    {
        $this->artisan('sitemap:generate', ['--path' => $this->path])->assertSuccessful();

        $xml = file_get_contents($this->path);

        $this->assertStringContainsString('<urlset', $xml);
        $this->assertStringNotContainsString('<sitemapindex', $xml);
        $this->assertStringContainsString('<loc>'.route('home').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('car-landing.index').'</loc>', $xml);
        $this->assertCount(1, glob(dirname($this->path).'/sitemap*.xml'));
    }

    public function test_removes_parts_left_by_the_previous_version(): void
    {
        foreach (['categories', 'brands', 'products', 'pages', 'cars'] as $type) {
            file_put_contents(dirname($this->path)."/sitemap-{$type}.xml", '<urlset/>');
        }

        $this->artisan('sitemap:generate', ['--path' => $this->path])->assertSuccessful();

        $this->assertSame([$this->path], glob(dirname($this->path).'/sitemap*.xml'));
    }

    public function test_has_only_brand_pages_with_enough_products(): void
    {
        $batteries = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori', 'status' => true]);
        $oils = Category::query()->create(['name' => 'Масла', 'slug' => 'motornoe-maslo', 'status' => true]);
        $titan = Manufacturer::query()->create(['name' => 'TITAN', 'slug' => 'titan']);
        $varta = Manufacturer::query()->create(['name' => 'VARTA', 'slug' => 'varta']);

        foreach (['1', '2', '3'] as $number) {
            $batteries->products()->attach(Product::query()->create(['name' => "TITAN {$number}", 'slug' => "titan-{$number}", 'manufacturer_id' => $titan->id, 'price' => 1, 'status' => true]));
            $oils->products()->attach(Product::query()->create(['name' => "Масло {$number}", 'slug' => "maslo-{$number}", 'manufacturer_id' => $titan->id, 'price' => 1, 'status' => true]));
        }

        $batteries->products()->attach(Product::query()->create(['name' => 'VARTA 1', 'slug' => 'varta-1', 'manufacturer_id' => $varta->id, 'price' => 1, 'status' => true]));

        $this->artisan('sitemap:generate', ['--path' => $this->path])->assertSuccessful();

        $xml = file_get_contents($this->path);

        $this->assertStringContainsString('<loc>'.route('category.manufacturer', [$batteries, $titan]).'</loc>', $xml);
        // Мало товаров, раздел вне списка и адреса с параметрами в карту не попадают.
        $this->assertStringNotContainsString('<loc>'.route('category.manufacturer', [$batteries, $varta]).'</loc>', $xml);
        $this->assertStringNotContainsString('<loc>'.route('category.manufacturer', [$oils, $titan]).'</loc>', $xml);
        $this->assertDoesNotMatchRegularExpression('/<loc>[^<]*\\?[^<]*<\\/loc>/', $xml);
    }

    public function test_has_only_active_guides(): void
    {
        $live = Guide::factory()->create(['slug' => 'live']);
        $hidden = Guide::factory()->create(['slug' => 'hidden', 'status' => false]);

        $this->artisan('sitemap:generate', ['--path' => $this->path])->assertSuccessful();

        $xml = file_get_contents($this->path);
        $this->assertStringContainsString('<loc>'.route('guide.show', $live).'</loc>', $xml);
        $this->assertStringNotContainsString(route('guide.show', $hidden), $xml);
    }

    public function test_has_main_pages_and_lastmod_follows_catalog(): void
    {
        Product::query()->create(['name' => 'Товар', 'slug' => 'tovar', 'price' => 1, 'status' => true]);
        $this->travelTo(now()->addDays(3)->startOfSecond());
        Product::query()->where('slug', 'tovar')->update(['price' => 2]);
        $changedAt = Product::query()->where('slug', 'tovar')->sole()->updated_at;

        $this->artisan('sitemap:generate', ['--path' => $this->path])->assertSuccessful();

        $xml = file_get_contents($this->path);

        foreach ([route('home'), route('contact'), route('business')] as $location) {
            $this->assertStringContainsString("<loc>{$location}</loc>", $xml);
        }

        $this->assertMatchesRegularExpression('#<loc>'.preg_quote(route('home'), '#').'</loc>\s*<lastmod>'.preg_quote($changedAt->toAtomString(), '#').'</lastmod>#', $xml);
        // Без параметров машины подбор отдаёт 404 — в карте ему не место.
        $this->assertStringNotContainsString('podbor-akb', $xml);
        $this->assertStringContainsString('<loc>'.route('car-landing.index').'</loc>', $xml);
        // У контактов своей даты изменения нет — не выдумываем.
        $this->assertDoesNotMatchRegularExpression('#<loc>'.preg_quote(route('contact'), '#').'</loc>\s*<lastmod>#', $xml);
    }

    public function test_does_not_overwrite_the_map_when_it_shrinks_sharply(): void
    {
        $category = Category::query()->create(['name' => 'Автотовары', 'slug' => 'avtotovari', 'status' => true]);
        $category->products()->attach(Product::query()->create(['name' => 'Компрессор', 'slug' => 'kompressor', 'price' => 1, 'status' => true]));

        // Прежняя карта на сто адресов, а в базе осталось несколько — это похоже на сбой, а не на правку каталога.
        $previous = '<urlset>'.str_repeat('<url><loc>https://example.test/x</loc></url>', 100).'</urlset>';
        file_put_contents($this->path, $previous);

        $this->artisan('sitemap:generate', ['--path' => $this->path])->assertFailed();

        $this->assertSame($previous, file_get_contents($this->path));

        $this->artisan('sitemap:generate', ['--path' => $this->path, '--force' => true])->assertSuccessful();

        $this->assertStringContainsString(route('product.show', 'kompressor'), file_get_contents($this->path));
    }

    public function test_leaves_no_temporary_file_and_replaces_the_old_map(): void
    {
        file_put_contents($this->path, '<urlset><url><loc>https://example.test/old</loc></url></urlset>');

        $this->artisan('sitemap:generate', ['--path' => $this->path])->assertSuccessful();

        $this->assertStringNotContainsString('example.test/old', file_get_contents($this->path));
        $this->assertSame([$this->path], glob(dirname($this->path).'/sitemap*'));
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
