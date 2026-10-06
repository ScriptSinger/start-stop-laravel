<?php

namespace App\Services;

use App\Enums\CatalogSort;
use App\Models\BatteryFitment;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Подбор АКБ по автомобилю: марка → модель → поколение → подходящие товары.
 * Порт catalog/controller/extension/module/battery_filter.php старого проекта;
 * сопоставление машины с товарами — BatteryFitment::matchingAttributeValueIds()
 * и Product::fitsBattery().
 */
class BatterySelection
{
    /**
     * Подпись поколения в списке, когда оно у машины не указано (таких 1480
     * записей). Фронт присылает её обратно как gen — в старом коде поиск по
     * generation = 'Стандарт' ничего не находил, и подбор для них не работал.
     */
    public const DEFAULT_GENERATION_LABEL = 'Стандарт';

    /**
     * 'LADA' в оригинальном battery_filter.php не совпадало ни с одной записью:
     * в данных марка называется «ВАЗ (Lada)».
     */
    private const POPULAR_BRANDS = [
        'ВАЗ (Lada)', 'Toyota', 'Hyundai', 'Kia', 'Renault', 'Volkswagen',
        'Skoda', 'Nissan', 'Ford', 'Chevrolet', 'BMW', 'Mercedes-Benz',
    ];

    /**
     * Марки для первого шага подбора, с логотипами.
     *
     * @return array{popular_brands: list<array{name: string, image: string}>, other_brands: list<array{name: string, image: string}>}
     */
    public function brands(): array
    {
        [$popular, $other] = BatteryFitment::query()
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand')
            ->map(fn (string $brand): array => ['name' => $brand, 'image' => $this->brandLogoUrl($brand)])
            ->partition(fn (array $brand): bool => in_array($brand['name'], self::POPULAR_BRANDS, true));

        return ['popular_brands' => $popular->values()->all(), 'other_brands' => $other->values()->all()];
    }

    /**
     * @return Collection<int, string>
     */
    public function models(string $brand): Collection
    {
        return BatteryFitment::query()
            ->where('brand', $brand)
            ->distinct()
            ->orderBy('model')
            ->pluck('model');
    }

    /**
     * @return Collection<int, array{name: string, image: string}>
     */
    public function generations(string $brand, string $model): Collection
    {
        return BatteryFitment::query()
            ->where('brand', $brand)
            ->where('model', $model)
            ->distinct()
            ->orderBy('generation')
            ->pluck('generation')
            ->map(function (?string $generation): array {
                $name = trim((string) $generation);

                return [
                    'name' => $name ?: self::DEFAULT_GENERATION_LABEL,
                    'image' => $this->generationImage($name),
                ];
            })
            ->values();
    }

    public function findFitment(string $brand, string $model, string $generation): ?BatteryFitment
    {
        $generation = trim($generation);

        return BatteryFitment::query()
            ->where('brand', $brand)
            ->where('model', $model)
            ->when(
                $generation === '' || $generation === self::DEFAULT_GENERATION_LABEL,
                fn ($query) => $query->where(fn ($query) => $query->whereNull('generation')->orWhere('generation', '')),
                fn ($query) => $query->where('generation', $generation),
            )
            // Для одной машины в базе бывают повторяющиеся строки — берём
            // всегда одну и ту же.
            ->orderBy('id')
            ->first();
    }

    /**
     * Подходящие аккумуляторы: сначала в наличии, потом под заказ, по цене.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function products(BatteryFitment $fitment, CatalogSort $sort, int $perPage): LengthAwarePaginator
    {
        return Product::query()
            ->where('status', true)
            ->fitsBattery($fitment)
            ->withCardData()
            ->sortedBy($sort)
            ->paginate($perPage);
    }

    private function brandLogoUrl(string $brand): string
    {
        $logoPath = 'catalog/carslogo/'.mb_strtolower($brand, 'UTF-8').'.png';

        return Storage::disk('public')->url(
            Storage::disk('public')->exists($logoPath) ? $logoPath : 'catalog/carslogo/no_image.png',
        );
    }

    /**
     * Имена файлов фото машин нормализованы скриптом fix_cars.php старого
     * проекта; варианты с «_» по краям — как в его battery_filter.php.
     */
    public function generationImage(string $generation): string
    {
        $fileName = str_replace([' - ', ' '], '_', $generation);
        $fileName = str_replace(['(', ')', '.', ','], '', $fileName);
        $fileName = trim((string) preg_replace('/__+/', '_', $fileName), '_');

        $imagePath = collect([
            "catalog/cars/{$fileName}_.jpg",
            "catalog/cars/{$fileName}.jpg",
            "catalog/cars/_{$fileName}.jpg",
            "catalog/cars/_{$fileName}_.jpg",
            'catalog/cars/'.mb_strtolower($fileName, 'UTF-8').'.jpg',
        ])->first(fn (string $path): bool => Storage::disk('public')->exists($path));

        // catalog/cars/no_image.jpg не существовал даже в старом проекте —
        // берём реальную заглушку из корня диска.
        return Storage::disk('public')->url($imagePath ?? 'no_image.png');
    }
}
