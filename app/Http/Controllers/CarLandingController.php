<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogFilterRequest;
use App\Models\BatteryFitment;
use App\Models\Category;
use App\Models\Product;
use App\Services\BatterySelection;
use App\Services\CarLanding\CarBrand;
use App\Services\CarLanding\CarGeneration;
use App\Services\CarLanding\CarLandingCatalog;
use App\Services\CarLanding\CarModel;
use App\Services\Seo\BreadcrumbsSeo;
use Artesaos\SEOTools\Facades\SEOMeta;
use Artesaos\SEOTools\Facades\SEOTools;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Посадочные страницы для поиска: «Аккумуляторы для Lada» (список моделей),
 * «Аккумулятор для Lada Vesta» и «… Lada Vesta I Рестайлинг 2022 - н.в.»
 * (подходящие товары и параметры).
 */
class CarLandingController extends Controller
{
    public function __construct(private BreadcrumbsSeo $breadcrumbs) {}

    public function index(CarLandingCatalog $catalog, BatterySelection $selection): View
    {
        $brands = $catalog->brandsWithModels();

        SEOTools::setTitle('Аккумуляторы для автомобилей всех марок — купить в Уфе | '.config('shop.name'), false);
        SEOTools::setDescription('Подбор аккумулятора по марке и модели автомобиля: '.$brands->count().' марок, ёмкость, полярность и размеры, цены и наличие в Уфе. Доставка по городу, трейд-ин.');
        $this->breadcrumbs([]);

        return view('car-landing.index', [
            'brands' => $brands,
            'logos' => $brands->mapWithKeys(fn (CarBrand $brand): array => [$brand->slug => $selection->brandLogo($brand->fitmentBrand)]),
            'modelCounts' => $brands->mapWithKeys(fn (CarBrand $brand): array => [$brand->slug => $catalog->models($brand)->count()]),
        ]);
    }

    public function brand(string $brand, CarLandingCatalog $catalog): View
    {
        $carBrand = $catalog->brand($brand) ?? abort(404);
        $models = $catalog->models($carBrand);

        abort_if($models->isEmpty(), 404);

        SEOTools::setTitle("Аккумуляторы для {$carBrand->name} — купить в Уфе | ".config('shop.name'), false);
        SEOTools::setDescription("Аккумуляторы для всех моделей {$carBrand->name} в Уфе: подбор по ёмкости, полярности и размерам, цены и наличие. Доставка по городу, трейд-ин старого АКБ.");
        $this->breadcrumbs([['name' => "Аккумуляторы для {$carBrand->name}", 'url' => route('car-landing.brand', $carBrand->slug)]]);

        return view('car-landing.brand', [
            'brand' => $carBrand,
            'models' => $models,
            'productCounts' => $models->mapWithKeys(fn (CarModel $model): array => [$model->slug => count($catalog->productIds($model))]),
        ]);
    }

    public function model(CatalogFilterRequest $request, string $brand, string $model, CarLandingCatalog $catalog): View
    {
        $carBrand = $catalog->brand($brand) ?? abort(404);
        $carModel = $catalog->model($carBrand, $model) ?? abort(404);

        return $this->show($request, $catalog, $carModel);
    }

    public function generation(CatalogFilterRequest $request, string $brand, string $model, string $generation, CarLandingCatalog $catalog): View
    {
        $carBrand = $catalog->brand($brand) ?? abort(404);
        $carModel = $catalog->model($carBrand, $model) ?? abort(404);
        $carGeneration = $catalog->generation($carModel, $generation) ?? abort(404);

        return $this->show($request, $catalog, $carModel, $carGeneration);
    }

    private function show(CatalogFilterRequest $request, CarLandingCatalog $catalog, CarModel $model, ?CarGeneration $generation = null): View
    {
        $subject = $generation ?? $model;
        $products = $catalog->products($subject, $request->sort(), $request->perPage())->withQueryString();
        $priceFrom = $this->priceFrom($catalog->productIds($subject));

        $this->describe($subject, $products, $priceFrom);

        $this->breadcrumbs(array_values(array_filter([
            ['name' => "Аккумуляторы для {$model->brand->name}", 'url' => route('car-landing.brand', $model->brand->slug)],
            ['name' => $model->name, 'url' => $model->url()],
            $generation ? ['name' => $generation->label, 'url' => $generation->url()] : null,
        ])));

        // Поколение с тем же набором аккумуляторов, что у всей модели, —
        // копия её страницы: поисковику показываем страницу модели.
        if ($generation !== null && ! $catalog->hasOwnProducts($generation)) {
            SEOMeta::setCanonical($model->url());
        }

        return view('car-landing.model', [
            'brand' => $model->brand,
            'model' => $model,
            'generation' => $generation,
            'subject' => $subject,
            'generations' => $catalog->generations($model),
            'engines' => $generation === null ? collect() : $this->engines($generation),
            'products' => $products,
            'priceFrom' => $priceFrom,
            'inStockCount' => Product::query()->whereKey($catalog->productIds($subject))->where('quantity', '>', 0)->count(),
            'otherModels' => $catalog->models($model->brand)->reject(fn (CarModel $other): bool => $other->slug === $model->slug),
            'sort' => $request->sort(),
            'perPage' => $request->perPage(),
            'batteryCategory' => Category::query()->where('status', true)->find(config('shop.battery_fitment.category_ids')[0] ?? null),
        ]);
    }

    /**
     * Крошки как в шаблонах: главная, «По марке авто» и страницы ниже по уровню.
     *
     * @param  list<array{name: string, url: string}>  $crumbs
     */
    private function breadcrumbs(array $crumbs): void
    {
        $this->breadcrumbs->apply([
            ['name' => config('shop.name'), 'url' => route('home')],
            ['name' => 'По марке авто', 'url' => route('car-landing.index')],
            ...$crumbs,
        ]);
    }

    /**
     * Двигатели поколения, которым подходят разные АКБ: ссылки на подбор
     * под конкретный мотор (страница поколения показывает варианты для всех).
     *
     * @return Collection<int, array{name: string, url: string}>
     */
    private function engines(CarGeneration $generation): Collection
    {
        // Двигатели записаны под тем же поколением, что и сама запись с мотором.
        $fitment = $generation->fitments->first(fn (BatteryFitment $fitment): bool => $fitment->engine !== null);

        if ($fitment === null) {
            return collect();
        }

        return app(BatterySelection::class)
            ->engines($fitment->brand, $fitment->model, (string) $fitment->generation)
            ->map(fn (string $engine): array => [
                'name' => $engine,
                'url' => route('battery-selection', ['brand' => $fitment->brand, 'model' => $fitment->model, 'gen' => $fitment->generation, 'engine' => $engine]),
            ]);
    }

    /**
     * Самая низкая цена среди подходящих товаров — «от N ₽» в тексте и описании.
     *
     * @param  list<int>  $productIds
     */
    private function priceFrom(array $productIds): ?int
    {
        $price = Product::query()
            ->whereKey($productIds)
            ->get()
            ->map(fn (Product $product): float => $product->displayPrice())
            ->min();

        return $price === null ? null : (int) floor($price);
    }

    /**
     * @param  LengthAwarePaginator<int, Product>  $products
     */
    private function describe(CarModel|CarGeneration $subject, LengthAwarePaginator $products, ?int $priceFrom): void
    {
        $name = $subject->fullName();
        $capacities = $subject->capacities();
        $capacity = $capacities === [] ? '' : ', ёмкость '.min($capacities).'–'.max($capacities).' Ач';
        $price = $priceFrom === null ? '' : ' от '.number_format($priceFrom, 0, '', ' ').' ₽';
        $count = $products->total().' '.app('translator')->getSelector()->choose('подходящий аккумулятор|подходящих аккумулятора|подходящих аккумуляторов', $products->total(), 'ru');

        SEOTools::setTitle("Аккумулятор для {$name} — купить в Уфе | ".config('shop.name'), false);
        SEOTools::setDescription("Аккумуляторы для {$name} в Уфе: {$count}{$price}{$capacity}. Доставка по городу, трейд-ин старого аккумулятора.");
    }
}
