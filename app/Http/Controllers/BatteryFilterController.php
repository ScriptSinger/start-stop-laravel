<?php

namespace App\Http\Controllers;

use App\Models\BatteryFitment;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Порт catalog/controller/extension/module/battery_filter.php старого проекта.
 * Шаги марка → модель → поколение перенесены почти 1:1.
 *
 * Результат подбора — своя страница (show) вместо редиректа на категорию с
 * параметрами OCFilter (?ocf=F13S2V...). Сопоставление машины с товарами —
 * BatteryFitment::matchingAttributeValueIds() и Product::fitsBattery().
 */
class BatteryFilterController extends Controller
{
    /**
     * Подпись поколения в списке, когда оно у машины не указано (таких 1480
     * записей). Фронт присылает её обратно как gen — в старом коде поиск по
     * generation = 'Стандарт' ничего не находил, и подбор для них не работал.
     */
    private const DEFAULT_GENERATION_LABEL = 'Стандарт';

    // 'LADA' в оригинальном battery_filter.php не совпадало ни с одной
    // реальной записью — в данных бренд называется "ВАЗ (Lada)" (58 строк).
    // Та же опечатка была и в старом коде (0 совпадений там же), поправили
    // заодно — в popular_brands раньше реально никогда не попадал.
    private const POPULAR_BRANDS = [
        'ВАЗ (Lada)', 'Toyota', 'Hyundai', 'Kia', 'Renault', 'Volkswagen',
        'Skoda', 'Nissan', 'Ford', 'Chevrolet', 'BMW', 'Mercedes-Benz',
    ];

    public static function brandsData(): array
    {
        $brands = BatteryFitment::query()
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');

        $popular = [];
        $other = [];

        foreach ($brands as $brandName) {
            $logoPath = 'catalog/carslogo/'.mb_strtolower($brandName, 'UTF-8').'.png';
            $image = Storage::disk('public')->exists($logoPath)
                ? Storage::disk('public')->url($logoPath)
                : Storage::disk('public')->url('catalog/carslogo/no_image.png');

            $brandInfo = ['name' => $brandName, 'image' => $image];

            if (in_array($brandName, self::POPULAR_BRANDS, true)) {
                $popular[] = $brandInfo;
            } else {
                $other[] = $brandInfo;
            }
        }

        return ['popular_brands' => $popular, 'other_brands' => $other];
    }

    public function getModels(Request $request): JsonResponse
    {
        $brand = (string) $request->query('brand', '');

        $models = BatteryFitment::where('brand', $brand)
            ->distinct()
            ->orderBy('model')
            ->pluck('model')
            ->map(fn ($model) => ['model' => $model]);

        return response()->json($models);
    }

    public function getGenerations(Request $request): JsonResponse
    {
        $brand = (string) $request->query('brand', '');
        $model = (string) $request->query('model', '');

        $generations = BatteryFitment::where('brand', $brand)
            ->where('model', $model)
            ->distinct()
            ->orderBy('generation')
            ->pluck('generation');

        $results = $generations->map(function (?string $generation) {
            $generationName = trim((string) $generation);

            $fileName = str_replace([' - ', ' '], '_', $generationName);
            $fileName = str_replace(['(', ')', '.', ','], '', $fileName);
            $fileName = preg_replace('/__+/', '_', $fileName);
            $fileName = trim($fileName, '_');

            $variants = [
                "catalog/cars/{$fileName}_.jpg",
                "catalog/cars/{$fileName}.jpg",
                "catalog/cars/_{$fileName}.jpg",
                "catalog/cars/_{$fileName}_.jpg",
                'catalog/cars/'.mb_strtolower($fileName, 'UTF-8').'.jpg',
            ];

            $imagePath = collect($variants)->first(fn ($path) => Storage::disk('public')->exists($path));

            // catalog/cars/no_image.jpg — тот самый мёртвый placeholder из
            // находки в Фазе 2 (не существовал даже в старом проекте), берём
            // реальный фолбэк из корня диска.
            return [
                'name' => $generationName ?: self::DEFAULT_GENERATION_LABEL,
                'image' => Storage::disk('public')->url($imagePath ?: 'no_image.png'),
            ];
        });

        return response()->json($results->values());
    }

    public function getResult(Request $request): JsonResponse
    {
        $fitment = $this->findFitment($request);

        if (! $fitment || ! $fitment->hasSelectionData()) {
            return response()->json([]);
        }

        return response()->json([
            'redirect' => route('battery-selection', [
                'brand' => $fitment->brand,
                'model' => $fitment->model,
                'gen' => $fitment->generation ?: null,
            ]),
        ]);
    }

    public function show(Request $request): View
    {
        $fitment = $this->findFitment($request);

        abort_if(! $fitment || ! $fitment->hasSelectionData(), 404);

        $products = Product::query()
            ->where('status', true)
            ->fitsBattery($fitment)
            // Сначала то, что можно купить: в наличии, потом под заказ.
            ->orderByRaw('quantity > 0 DESC')
            ->orderByRaw('supplier_quantity >= ? DESC', [config('shop.supplier_order_min_quantity')])
            ->orderBy('price')
            ->paginate(24)
            ->withQueryString();

        return view('battery-selection', [
            'fitment' => $fitment,
            'products' => $products,
        ]);
    }

    private function findFitment(Request $request): ?BatteryFitment
    {
        $generation = trim((string) $request->query('gen', ''));

        return BatteryFitment::query()
            ->where('brand', (string) $request->query('brand', ''))
            ->where('model', (string) $request->query('model', ''))
            ->when(
                $generation === '' || $generation === self::DEFAULT_GENERATION_LABEL,
                fn ($query) => $query->where(fn ($query) => $query->whereNull('generation')->orWhere('generation', '')),
                fn ($query) => $query->where('generation', $generation),
            )
            ->first();
    }
}
