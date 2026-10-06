<?php

namespace App\Services\CarLanding;

use App\Enums\CatalogSort;
use App\Models\BatteryFitment;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Посадочные страницы «Аккумулятор для <модель>»: модели марки из данных
 * подбора и подходящие им товары.
 *
 * В данных одна модель записана по-разному («ВАЗ (Lada) Largus» и «Largus»)
 * — склеиваем по названию без марки. Страница есть только у модели, для
 * которой нашёлся хотя бы один товар: пустые автоматические страницы
 * поисковики считают мусором и понижают за них весь сайт.
 *
 * Расчёт тяжёлый (у Toyota около секунды), поэтому модели и наборы товаров
 * лежат в общем кеше. Каждую ночь refresh() считает всё заново под новой
 * версией ключей (car-landings:refresh в routes/console.php) — днём их
 * никто не считает. Цены и наличие на страницах берутся из базы как есть.
 */
class CarLandingCatalog
{
    private const CACHE_HOURS = 26;

    private const VERSION_KEY = 'car-landings:version';

    /**
     * @var array<string, list<int>>
     */
    private array $productIdsCache = [];

    /**
     * @var array<string, Collection<int, CarModel>>
     */
    private array $modelsCache = [];

    public function brand(string $slug): ?CarBrand
    {
        $config = config("shop.car_landings.brands.{$slug}");

        return $config === null ? null : new CarBrand($slug, $config['name'], $config['fitment_brand'], $config['skip_models'] ?? []);
    }

    /**
     * @return Collection<int, CarBrand>
     */
    public function brands(): Collection
    {
        return collect(array_keys(config('shop.car_landings.brands')))
            ->map(fn (string $slug): CarBrand => $this->brand($slug));
    }

    /**
     * Модели марки, для которых есть товары, по названию.
     *
     * @return Collection<int, CarModel>
     */
    public function models(CarBrand $brand): Collection
    {
        return $this->modelsCache[$brand->slug] ??= $this->hydrateModels($brand, Cache::remember(
            $this->cacheKey("models:{$brand->slug}"),
            now()->addHours(self::CACHE_HOURS),
            fn (): array => $this->findModels($brand)
                ->map(fn (CarModel $model): array => ['name' => $model->name, 'slug' => $model->slug, 'fitment_ids' => $model->fitments->modelKeys()])
                ->all(),
        ));
    }

    /**
     * Кеш хранит только простые данные — объекты Laravel из кеша не
     * восстанавливает (cache.serializable_classes): собираем модели заново
     * по id записей подбора, одним запросом.
     *
     * @param  list<array{name: string, slug: string, fitment_ids: list<int>}>  $rows
     * @return Collection<int, CarModel>
     */
    private function hydrateModels(CarBrand $brand, array $rows): Collection
    {
        $fitments = BatteryFitment::query()
            ->whereKey(array_merge(...array_column($rows, 'fitment_ids')))
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        return collect($rows)->map(fn (array $row): CarModel => new CarModel(
            $brand,
            $row['slug'],
            $row['name'],
            collect($row['fitment_ids'])->map(fn (int $id): ?BatteryFitment => $fitments->get($id))->filter()->values(),
        ));
    }

    /**
     * Пересчитать всё для всех марок под новой версией ключей: старые
     * значения доживают своё и удаляются по сроку.
     *
     * @return int сколько страниц (моделей и поколений) посчитано
     */
    public function refresh(): int
    {
        Cache::forever(self::VERSION_KEY, $this->version() + 1);
        $this->modelsCache = [];
        $this->productIdsCache = [];

        return $this->brands()->sum(fn (CarBrand $brand): int => $this->models($brand)
            ->sum(fn (CarModel $model): int => 1 + $this->generations($model)->count()));
    }

    /**
     * @return Collection<int, CarModel>
     */
    private function findModels(CarBrand $brand): Collection
    {
        return BatteryFitment::query()
            ->where('brand', $brand->fitmentBrand)
            ->orderBy('id')
            ->get()
            ->filter(fn (BatteryFitment $fitment): bool => $fitment->hasSelectionData())
            ->groupBy(fn (BatteryFitment $fitment): string => $this->modelName($brand, $fitment))
            ->reject(fn (Collection $fitments, string $name): bool => in_array($name, $brand->skipModels, true))
            ->map(fn (Collection $fitments, string $name): CarModel => new CarModel($brand, Str::slug($name), $name, $fitments->values()))
            ->filter(fn (CarModel $model): bool => $model->slug !== '' && $this->productIds($model) !== [])
            ->sortBy(fn (CarModel $model): string => $model->name, SORT_NATURAL)
            ->values();
    }

    /**
     * Включённая марка по тому, как она записана в данных подбора.
     */
    public function brandForFitment(string $fitmentBrand): ?CarBrand
    {
        return $this->brands()->first(fn (CarBrand $brand): bool => $brand->fitmentBrand === $fitmentBrand);
    }

    /**
     * Страница модели для записи подбора («ВАЗ (Lada)» + «Vesta»), если она есть.
     */
    public function modelFor(string $fitmentBrand, string $fitmentModel): ?CarModel
    {
        $brand = $this->brandForFitment($fitmentBrand);

        if ($brand === null) {
            return null;
        }

        $name = $this->modelName($brand, new BatteryFitment(['model' => $fitmentModel]));

        return $this->models($brand)->first(fn (CarModel $model): bool => $model->name === $name);
    }

    public function model(CarBrand $brand, string $slug): ?CarModel
    {
        return $this->models($brand)->first(fn (CarModel $model): bool => $model->slug === $slug);
    }

    /**
     * Поколения модели, для которых есть товары.
     *
     * @return Collection<int, CarGeneration>
     */
    public function generations(CarModel $model): Collection
    {
        return $model->generations()
            ->filter(fn (CarGeneration $generation): bool => $this->productIds($generation) !== [])
            ->values();
    }

    public function generation(CarModel $model, string $slug): ?CarGeneration
    {
        return $this->generations($model)->first(fn (CarGeneration $generation): bool => $generation->slug === $slug);
    }

    /**
     * Свой набор аккумуляторов, а не тот же, что у всей модели. Иначе
     * страница поколения — копия страницы модели: в поиск отдаём только её.
     */
    public function hasOwnProducts(CarGeneration $generation): bool
    {
        return $this->productIds($generation) !== $this->productIds($generation->model);
    }

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function products(CarModel|CarGeneration $subject, CatalogSort $sort, int $perPage): LengthAwarePaginator
    {
        return Product::query()
            ->whereKey($this->productIds($subject))
            ->withCardData()
            ->sortedBy($sort)
            ->paginate($perPage);
    }

    /**
     * Товары, подходящие хоть одной записи модели или поколения. Одинаковые
     * по параметрам записи проверяем один раз.
     *
     * @return list<int>
     */
    public function productIds(CarModel|CarGeneration $subject): array
    {
        return $this->productIdsCache[$subject->key()] ??= Cache::remember(
            $this->cacheKey('products:'.$subject->key()),
            now()->addHours(self::CACHE_HOURS),
            fn (): array => $this->findProductIds($subject),
        );
    }

    /**
     * @return list<int>
     */
    private function findProductIds(CarModel|CarGeneration $subject): array
    {
        return $subject->fitments
            ->unique(fn (BatteryFitment $fitment): string => $fitment->capacity.'|'.$fitment->polarity.'|'.$fitment->dims)
            ->flatMap(fn (BatteryFitment $fitment): Collection => Product::query()
                ->where('status', true)
                ->fitsBattery($fitment)
                ->pluck('id'))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * «ВАЗ (Lada) Largus» и «Largus» → «Largus».
     */
    private function modelName(CarBrand $brand, BatteryFitment $fitment): string
    {
        $model = trim((string) $fitment->model);

        if (str_starts_with(mb_strtolower($model), mb_strtolower($brand->fitmentBrand))) {
            $model = trim(mb_substr($model, mb_strlen($brand->fitmentBrand)));
        }

        return $model;
    }

    private function cacheKey(string $suffix): string
    {
        return 'car-landings:v'.$this->version().':'.$suffix;
    }

    private function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }
}
