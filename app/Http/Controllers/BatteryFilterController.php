<?php

namespace App\Http\Controllers;

use App\Models\BatteryFitment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Порт catalog/controller/extension/module/battery_filter.php старого проекта.
 * Сама логика подбора (марка → модель → поколение) переносится почти 1:1 —
 * она маленькая и не завязана на остальные фичи темы.
 *
 * getResult() — единственное существенное отличие от оригинала: там в конце
 * строился редирект на страницу категории с параметрами OCFilter
 * (?ocf=F13S2V...), которых у нас пока нет (это Фаза 4 плана — свой фильтр).
 * Пока редиректим на поиск по ёмкости среди товаров — рабочий, но временный
 * стенд-ин до нормального параметрического подбора.
 */
class BatteryFilterController extends Controller
{
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
                'name' => $generationName ?: 'Стандарт',
                'image' => Storage::disk('public')->url($imagePath ?: 'no_image.png'),
            ];
        });

        return response()->json($results->values());
    }

    public function getResult(Request $request): JsonResponse
    {
        $brand = (string) $request->query('brand', '');
        $model = (string) $request->query('model', '');
        $generation = (string) $request->query('gen', '');

        $query = BatteryFitment::where('brand', $brand)->where('model', $model);

        if ($generation !== '') {
            $query->where('generation', $generation);
        }

        $fitment = $query->first();

        if (! $fitment || ! $fitment->capacity) {
            return response()->json([]);
        }

        // Берём первое число из "60,62" и т.п. — в названиях товаров ёмкость
        // записана как "82 Ah", этого достаточно для поиска-стенд-ина.
        preg_match('/\d+/', $fitment->capacity, $matches);
        $capacity = $matches[0] ?? null;

        if (! $capacity) {
            return response()->json([]);
        }

        return response()->json([
            'redirect' => route('home', ['search' => "{$capacity} Ah"]),
        ]);
    }
}
