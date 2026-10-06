<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Services\CarLanding\CarBrand;
use App\Services\CarLanding\CarGeneration;
use App\Services\CarLanding\CarLandingCatalog;
use App\Services\CarLanding\CarModel;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

#[Signature('sitemap:generate {--path= : Куда записать файл (по умолчанию public/sitemap.xml)}')]
#[Description('Собрать sitemap.xml из базы: только включённые товары, страницы и разделы с товарами')]
class GenerateSitemap extends Command
{
    /**
     * Карта пересобирается каждую ночь (routes/console.php) и после импорта,
     * поэтому выключенный товар уходит из неё не позже чем через сутки.
     * На старом сайте файл выгрузили один раз в 2024 году — к 2026-му
     * половина адресов в нём отдавала 404.
     */
    public function handle(CarLandingCatalog $carLandings): int
    {
        $sitemap = Sitemap::create()
            ->add(Url::create(route('home')))
            ->add(Url::create(route('contact')));

        $categories = $this->categoriesWithProducts();
        $categories->each(fn (Category $category) => $sitemap->add(
            Url::create(route('category.show', $category))->setLastModificationDate($category->updated_at),
        ));

        $products = 0;
        Product::query()
            ->where('status', true)
            ->select(['id', 'slug', 'updated_at'])
            ->orderBy('id')
            ->chunk(500, function (Collection $chunk) use ($sitemap, &$products): void {
                $chunk->each(fn (Product $product) => $sitemap->add(
                    Url::create(route('product.show', $product))->setLastModificationDate($product->updated_at),
                ));
                $products += $chunk->count();
            });

        $pages = Page::query()->where('status', true)->orderBy('sort_order')->get();
        $pages->each(fn (Page $page) => $sitemap->add(
            Url::create(route('page.show', $page))->setLastModificationDate($page->updated_at),
        ));

        // Посадочные «Аккумулятор для …» — только включённые марки и модели с товарами.
        $carPages = 0;
        $carLandings->brands()->each(function (CarBrand $brand) use ($sitemap, $carLandings, &$carPages): void {
            $models = $carLandings->models($brand);

            if ($models->isEmpty()) {
                return;
            }

            $sitemap->add(Url::create(route('car-landing.brand', $brand->slug)));
            $carPages++;

            $models->each(function (CarModel $model) use ($sitemap, $carLandings, &$carPages): void {
                $sitemap->add(Url::create($model->url()));
                $carPages++;

                // Поколения — только со своим набором аккумуляторов, иначе это копии страницы модели.
                $carLandings->generations($model)
                    ->filter(fn (CarGeneration $generation): bool => $carLandings->hasOwnProducts($generation))
                    ->each(function (CarGeneration $generation) use ($sitemap, &$carPages): void {
                        $sitemap->add(Url::create($generation->url()));
                        $carPages++;
                    });
            });
        });

        $path = $this->option('path') ?: public_path('sitemap.xml');
        $sitemap->writeToFile($path);

        $this->info("sitemap.xml: разделов {$categories->count()}, товаров {$products}, страниц {$pages->count()}, подбор по авто {$carPages} → {$path}");

        return self::SUCCESS;
    }

    /**
     * Включённые разделы, где есть включённые товары — в самом разделе или
     * в подразделах. Пустые разделы в карту не попадают: для поисковика это
     * страницы без содержимого.
     *
     * @return Collection<int, Category>
     */
    private function categoriesWithProducts(): Collection
    {
        $categories = Category::query()->where('status', true)->orderBy('sort_order')->orderBy('id')->get()->keyBy('id');

        $withProducts = DB::table('category_product')
            ->join('products', 'products.id', '=', 'category_product.product_id')
            ->where('products.status', true)
            ->distinct()
            ->pluck('category_product.category_id');

        // Раздел с товарами делает непустыми и всех своих родителей.
        $keep = [];
        foreach ($withProducts as $categoryId) {
            for ($category = $categories->get($categoryId); $category && ! isset($keep[$category->id]); $category = $categories->get($category->parent_id)) {
                $keep[$category->id] = true;
            }
        }

        return $categories->only(array_keys($keep))->values();
    }
}
