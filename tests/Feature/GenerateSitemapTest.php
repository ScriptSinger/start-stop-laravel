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
        array_map('unlink', glob(dirname($this->path).'/sitemap*.xml'));

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

        $xml = $this->allSitemaps();

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

    public function test_index_lists_files_by_type(): void
    {
        $this->artisan('sitemap:generate', ['--path' => $this->path])->assertSuccessful();

        $index = file_get_contents($this->path);

        foreach (['categories', 'brands', 'products', 'pages', 'cars'] as $type) {
            $this->assertStringContainsString('<loc>'.url("sitemap-{$type}.xml").'</loc>', $index);
            $this->assertFileExists(dirname($this->path)."/sitemap-{$type}.xml");
        }

        // Адреса страниц лежат в файлах по типам, а не в индексе.
        $this->assertStringNotContainsString(route('home'), str_replace(url('sitemap-'), '', $index));
    }

    public function test_brands_file_has_only_brand_pages_with_enough_products(): void
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

        $brands = file_get_contents(dirname($this->path).'/sitemap-brands.xml');

        $this->assertStringContainsString('<loc>'.route('category.manufacturer', [$batteries, $titan]).'</loc>', $brands);
        // Мало товаров, раздел вне списка и адреса с параметрами в карту не попадают.
        $this->assertStringNotContainsString('varta', $brands);
        $this->assertStringNotContainsString('motornoe-maslo', $brands);
        $this->assertDoesNotMatchRegularExpression('/<loc>[^<]*\\?[^<]*<\\/loc>/', $brands);
    }

    public function test_pages_file_has_only_active_guides(): void
    {
        $live = Guide::factory()->create(['slug' => 'live']);
        $hidden = Guide::factory()->create(['slug' => 'hidden', 'status' => false]);

        $this->artisan('sitemap:generate', ['--path' => $this->path])->assertSuccessful();

        $pages = file_get_contents(dirname($this->path).'/sitemap-pages.xml');
        $this->assertStringContainsString('<loc>'.route('guide.show', $live).'</loc>', $pages);
        $this->assertStringNotContainsString(route('guide.show', $hidden), $pages);
    }

    public function test_pages_file_has_main_pages_and_lastmod_follows_catalog(): void
    {
        Product::query()->create(['name' => 'Товар', 'slug' => 'tovar', 'price' => 1, 'status' => true]);
        $this->travelTo(now()->addDays(3)->startOfSecond());
        Product::query()->where('slug', 'tovar')->update(['price' => 2]);
        $changedAt = Product::query()->where('slug', 'tovar')->sole()->updated_at;

        $this->artisan('sitemap:generate', ['--path' => $this->path])->assertSuccessful();

        $pages = file_get_contents(dirname($this->path).'/sitemap-pages.xml');
        $cars = file_get_contents(dirname($this->path).'/sitemap-cars.xml');

        foreach ([route('home'), route('contact'), route('business')] as $location) {
            $this->assertStringContainsString("<loc>{$location}</loc>", $pages);
        }

        $this->assertMatchesRegularExpression('#<loc>'.preg_quote(route('home'), '#').'</loc>\s*<lastmod>'.preg_quote($changedAt->toAtomString(), '#').'</lastmod>#', $pages);
        // Без параметров машины подбор отдаёт 404 — в карте ему не место.
        $this->assertStringNotContainsString('podbor-akb', $cars);
        $this->assertStringContainsString('<loc>'.route('car-landing.index').'</loc>', $cars);
        // У контактов своей даты изменения нет — не выдумываем.
        $this->assertDoesNotMatchRegularExpression('#<loc>'.preg_quote(route('contact'), '#').'</loc>\s*<lastmod>#', $pages);
    }

    public function test_rebuilt_every_night(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'sitemap:generate'));

        $this->assertNotNull($event);
        $this->assertSame('15 3 * * *', $event->expression);
        $this->assertSame('Asia/Yekaterinburg', $event->timezone);
    }

    private function allSitemaps(): string
    {
        return collect(glob(dirname($this->path).'/sitemap*.xml'))->map(file_get_contents(...))->implode("\n");
    }
}
