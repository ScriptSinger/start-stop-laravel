<?php

namespace App\Console\Commands;

use App\Models\BatteryFitment;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Sitemap as SitemapTag;
use Spatie\Sitemap\Tags\Url;

#[Signature('sitemap:generate {--path= : Куда записать индекс (по умолчанию public/sitemap.xml); файлы по типам лягут рядом}')]
#[Description('Собрать sitemap.xml из базы: индекс и файлы по типам — только включённые товары, страницы и разделы с товарами')]
class GenerateSitemap extends Command
{
    /**
     * Карта пересобирается каждую ночь (routes/console.php) и после импорта,
     * поэтому выключенный товар уходит из неё не позже чем через сутки.
     * На старом сайте файл выгрузили один раз в 2024 году — к 2026-му
     * половина адресов в нём отдавала 404.
     *
     * sitemap.xml — индекс, сами адреса лежат в sitemap-categories.xml,
     * sitemap-products.xml, sitemap-pages.xml и sitemap-cars.xml: так в
     * Вебмастере и Search Console видно, какой тип страниц не индексируется.
     */
    public function handle(CarLandingCatalog $carLandings): int
    {
        $path = $this->option('path') ?: public_path('sitemap.xml');
        $directory = dirname($path);

        // Дата свежего товара — «изменение» главной, подбора и посадочных по авто:
        // их содержимое (цены, наличие, список аккумуляторов) собирается из товаров.
        $catalogChangedAt = $this->catalogChangedAt();

        [$categorySitemap, $categories] = $this->categorySitemap();
        [$productSitemap, $products] = $this->productSitemap();
        [$pageSitemap, $pages] = $this->pageSitemap($catalogChangedAt);
        [$carSitemap, $carPages] = $this->carSitemap($carLandings, $catalogChangedAt);

        $index = SitemapIndex::create();

        foreach ([
            'sitemap-categories.xml' => $categorySitemap,
            'sitemap-products.xml' => $productSitemap,
            'sitemap-pages.xml' => $pageSitemap,
            'sitemap-cars.xml' => $carSitemap,
        ] as $file => $sitemap) {
            $sitemap->writeToFile("{$directory}/{$file}");
            $index->add(SitemapTag::create(url($file))->setLastModificationDate(now()));
        }

        $index->writeToFile($path);

        $this->info("sitemap.xml: разделов {$categories}, товаров {$products}, страниц {$pages}, подбор по авто {$carPages} → {$path}");

        return self::SUCCESS;
    }

    /**
     * @return array{Sitemap, int}
     */
    private function categorySitemap(): array
    {
        $sitemap = Sitemap::create();
        $categories = $this->categoriesWithProducts();

        $categories->each(fn (Category $category) => $sitemap->add(
            Url::create(route('category.show', $category))->setLastModificationDate($category->updated_at),
        ));

        return [$sitemap, $categories->count()];
    }

    /**
     * @return array{Sitemap, int}
     */
    private function productSitemap(): array
    {
        $sitemap = Sitemap::create();
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

        return [$sitemap, $products];
    }

    /**
     * Главная, контакты, страница для организаций и инфостраницы.
     *
     * @return array{Sitemap, int}
     */
    private function pageSitemap(?Carbon $catalogChangedAt): array
    {
        $sitemap = Sitemap::create()
            ->add($this->url(route('home'), $catalogChangedAt))
            ->add(Url::create(route('contact')))
            ->add(Url::create(route('business')));

        $pages = Page::query()->where('status', true)->orderBy('sort_order')->get();
        $pages->each(fn (Page $page) => $sitemap->add(
            Url::create(route('page.show', $page))->setLastModificationDate($page->updated_at),
        ));

        return [$sitemap, $pages->count() + 3];
    }

    /**
     * Посадочные «Аккумулятор для …» — только включённые марки и модели
     * с товарами.
     *
     * @return array{Sitemap, int}
     */
    private function carSitemap(CarLandingCatalog $carLandings, ?Carbon $catalogChangedAt): array
    {
        $changedAt = $this->latest($catalogChangedAt, BatteryFitment::query()->max('updated_at'));

        // /podbor-akb сюда не входит: без параметров машины он отдаёт 404 —
        // это страница результата подбора, а не общая страница.
        $sitemap = Sitemap::create()
            ->add($this->url(route('car-landing.index'), $changedAt));
        $carPages = 1;

        $carLandings->brands()->each(function (CarBrand $brand) use ($sitemap, $carLandings, $changedAt, &$carPages): void {
            $models = $carLandings->models($brand);

            if ($models->isEmpty()) {
                return;
            }

            $sitemap->add($this->url(route('car-landing.brand', $brand->slug), $changedAt));
            $carPages++;

            $models->each(function (CarModel $model) use ($sitemap, $carLandings, $changedAt, &$carPages): void {
                $sitemap->add($this->url($model->url(), $changedAt));
                $carPages++;

                // Поколения — только со своим набором аккумуляторов, иначе это копии страницы модели.
                $carLandings->generations($model)
                    ->filter(fn (CarGeneration $generation): bool => $carLandings->hasOwnProducts($generation))
                    ->each(function (CarGeneration $generation) use ($sitemap, $changedAt, &$carPages): void {
                        $sitemap->add($this->url($generation->url(), $changedAt));
                        $carPages++;
                    });
            });

            $carLandings->releaseMemory();
        });

        return [$sitemap, $carPages];
    }

    private function url(string $location, ?Carbon $changedAt): Url
    {
        $url = Url::create($location);

        return $changedAt === null ? $url : $url->setLastModificationDate($changedAt);
    }

    private function catalogChangedAt(): ?Carbon
    {
        return $this->latest(Product::query()->where('status', true)->max('updated_at'));
    }

    private function latest(mixed ...$dates): ?Carbon
    {
        $dates = array_map(fn (mixed $date): ?Carbon => $date === null ? null : Carbon::parse($date), $dates);

        return collect($dates)->filter()->max();
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
            ->whereNull('products.deleted_at')
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
