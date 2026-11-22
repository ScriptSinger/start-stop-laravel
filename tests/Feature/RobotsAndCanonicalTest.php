<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RobotsAndCanonicalTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_txt_closes_only_json_endpoints_and_merges_parameters_for_yandex(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        foreach (['/cart/state', '/category/*/count', '/battery-filter/', '/auth/'] as $path) {
            $this->assertStringContainsString("Disallow: {$path}", $robots);
        }

        $this->assertStringContainsString('Clean-param: sort&limit&price_from&price_to&available&manufacturer&case_type&attr /category/', $robots);
        $this->assertStringContainsString('Sitemap: https://start-stop.su/sitemap.xml', $robots);
    }

    public function test_pages_that_rely_on_noindex_stay_open_for_crawling(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        // Закрытая в robots.txt страница не отдаёт роботу noindex и canonical.
        $disallowed = collect(preg_split('/\R/', $robots))
            ->filter(fn (string $line): bool => str_starts_with($line, 'Disallow:'))
            ->map(fn (string $line): string => trim(substr($line, 9)));

        foreach (['/cart', '/checkout', '/search', '/wishlist', '/compare-products', '/my-account'] as $path) {
            $this->assertFalse($disallowed->contains($path), "{$path} закрыт в robots.txt");
        }

        $this->assertEmpty($disallowed->filter(fn (string $path): bool => str_contains($path, '?') || str_contains($path, '=')));
    }

    public function test_every_service_page_is_noindex(): void
    {
        foreach (['cart.index', 'search', 'wishlist.index', 'compare.index'] as $route) {
            $this->get(route($route))->assertSee('<meta name="robots" content="noindex, follow">', false);
        }

        // С пустой корзиной оформление уводит в корзину, поэтому проверяем сам маршрут.
        $this->assertContains('noindex', Route::getRoutes()->getByName('checkout.create')->gatherMiddleware());
    }

    public function test_category_variants_point_canonical_to_the_clean_address(): void
    {
        $category = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori']);
        $category->products()->attach(Product::query()->create(['name' => 'ZUBR', 'slug' => 'zubr', 'price' => 1, 'status' => true]));

        $this->get(route('category.show', ['category' => $category, 'sort' => 'price_asc', 'limit' => 50, 'price_from' => 100, 'manufacturer' => [1]]))
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('category.show', $category).'">', false);
    }
}
