<?php

namespace App\Console\Commands;

use App\Models\BatteryFitment;
use App\Models\Category;
use App\Models\Guide;
use App\Models\Page;
use App\Models\Product;
use App\Services\CarLanding\CarBrand;
use App\Services\CarLanding\CarGeneration;
use App\Services\CarLanding\CarLandingCatalog;
use App\Services\CarLanding\CarModel;
use App\Services\Catalog\BrandPages;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

#[Signature('sitemap:generate {--path= : Куда записать файл (по умолчанию public/sitemap.xml)} {--force : Записать, даже если адресов стало меньше половины}')]
#[Description('Собрать sitemap.xml из базы: только включённые товары, страницы и разделы с товарами')]
class GenerateSitemap extends Command
{
    /**
     * Старые части карты (индекс и файлы по типам), которые писала прежняя версия
     * команды. После перехода на один файл их удаляют, чтобы на сервере не лежали
     * устаревшие адреса.
     */
    private const LEGACY_PARTS = ['categories', 'brands', 'products', 'pages', 'cars'];

    /**
     * Карта пересобирается каждую ночь (routes/console.php) и после импорта,
     * поэтому выключенный товар уходит из неё не позже чем через сутки.
     * На старом сайте файл выгрузили один раз в 2024 году — к 2026-му
     * половина адресов в нём отдавала 404.
     *
     * Все адреса лежат в одном sitemap.xml: их около шести тысяч, а лимит
     * протокола — 50 000 адресов и 50 МБ на файл.
     *
     * Запись атомарная (временный файл и переименование): поисковый робот,
     * пришедший во время пересборки, получает целую карту, а не обрывок.
     * Если адресов стало меньше половины прежнего, карта не перезаписывается:
     * так пустая или неполная база не заставит поисковики выбросить страницы.
     */
    public function handle(CarLandingCatalog $carLandings, BrandPages $brandPages): int
    {
        $path = $this->option('path') ?: public_path('sitemap.xml');

        // Дата свежего товара — «изменение» главной, подбора и посадочных по авто:
        // их содержимое (цены, наличие, список аккумуляторов) собирается из товаров.
        $catalogChangedAt = $this->catalogChangedAt();

        $sitemap = Sitemap::create();

        $pages = $this->addPages($sitemap, $catalogChangedAt);
        $categories = $this->addCategories($sitemap);
        $brands = $this->addBrands($sitemap, $brandPages);
        $products = $this->addProducts($sitemap);
        $carPages = $this->addCarLandings($sitemap, $carLandings, $catalogChangedAt);

        $urls = count($sitemap->getTags());
        $previous = is_file($path) ? substr_count((string) file_get_contents($path), '<loc>') : 0;

        if (! $this->option('force') && $previous > 0 && $urls * 2 < $previous) {
            $this->error("sitemap.xml не перезаписан: адресов {$urls} вместо прежних {$previous}. Проверьте базу или запустите с --force.");

            return self::FAILURE;
        }

        $temporary = "{$path}.tmp";
        $sitemap->writeToFile($temporary);
        File::move($temporary, $path);

        foreach (self::LEGACY_PARTS as $type) {
            File::delete(dirname($path)."/sitemap-{$type}.xml");
        }

        $this->info("sitemap.xml: разделов {$categories}, страниц производителей {$brands}, товаров {$products}, страниц {$pages}, подбор по авто {$carPages} → {$path}");

        return self::SUCCESS;
    }

    /**
     * Включённые разделы с товарами. Возвращает число добавленных адресов.
     */
    private function addCategories(Sitemap $sitemap): int
    {
        $categories = $this->categoriesWithProducts();

        $categories->each(fn (Category $category) => $sitemap->add(
            Url::create(route('category.show', $category))->setLastModificationDate($category->updated_at),
        ));

        return $categories->count();
    }

    /**
     * Страницы «раздел + производитель»: только пары с достаточным числом
     * товаров, без адресов с фильтрами. Возвращает число добавленных адресов.
     */
    private function addBrands(Sitemap $sitemap, BrandPages $brandPages): int
    {
        $pages = $brandPages->pages();

        $pages->each(fn (array $page) => $sitemap->add(
            $this->url(route('category.manufacturer', [$page['category'], $page['manufacturer']]), $page['changed_at'] ?? null),
        ));

        return $pages->count();
    }

    /**
     * Включённые товары. Возвращает число добавленных адресов.
     */
    private function addProducts(Sitemap $sitemap): int
    {
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

        return $products;
    }

    /**
     * Главная, контакты, страница для организаций и инфостраницы.
     * Возвращает число добавленных адресов.
     */
    private function addPages(Sitemap $sitemap, ?Carbon $catalogChangedAt): int
    {
        $sitemap
            ->add($this->url(route('home'), $catalogChangedAt))
            ->add(Url::create(route('contact')))
            ->add(Url::create(route('business')));

        $pages = Page::query()->where('status', true)->orderBy('sort_order')->get();
        $pages->each(fn (Page $page) => $sitemap->add(
            Url::create(route('page.show', $page))->setLastModificationDate($page->updated_at),
        ));

        $guides = Guide::query()->where('status', true)->orderBy('sort_order')->get();
        $guides->each(fn (Guide $guide) => $sitemap->add(
            Url::create(route('guide.show', $guide))->setLastModificationDate($guide->updated_at),
        ));

        return $pages->count() + $guides->count() + 3;
    }

    /**
     * Посадочные «Аккумулятор для …» — только включённые марки и модели
     * с товарами. Возвращает число добавленных адресов.
     */
    private function addCarLandings(Sitemap $sitemap, CarLandingCatalog $carLandings, ?Carbon $catalogChangedAt): int
    {
        $changedAt = $this->latest($catalogChangedAt, BatteryFitment::query()->max('updated_at'));

        // /podbor-akb сюда не входит: без параметров машины он отдаёт 404 —
        // это страница результата подбора, а не общая страница.
        $sitemap->add($this->url(route('car-landing.index'), $changedAt));
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

        return $carPages;
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
