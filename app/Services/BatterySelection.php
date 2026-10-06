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
            ->map(fn (string $brand): array => ['name' => $brand, 'image' => $this->brandLogo($brand)])
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
     * Поколения модели. Строка модели без поколения — общие данные модели:
     * отдельной плиткой («Стандарт») она нужна, только если поколений нет.
     * engines — у поколения есть двигатели с разными АКБ, нужен ещё шаг.
     *
     * @return Collection<int, array{name: string, image: string, engines: bool}>
     */
    public function generations(string $brand, string $model): Collection
    {
        $generations = BatteryFitment::query()
            ->where('brand', $brand)
            ->where('model', $model)
            ->whereNotNull('generation')
            ->where('generation', '!=', '')
            ->distinct()
            ->pluck('generation')
            ->sort(SORT_NATURAL)
            ->values();

        if ($generations->isEmpty()) {
            return collect([[
                'name' => self::DEFAULT_GENERATION_LABEL,
                'image' => $this->generationImage(''),
                'engines' => false,
            ]]);
        }

        return $generations->map(fn (string $generation): array => [
            'name' => $generation,
            'image' => $this->generationImage((new BatteryFitment(['brand' => $brand, 'model' => $model, 'generation' => $generation]))->displayName(withEngine: false)),
            'engines' => $this->engines($brand, $model, $generation)->isNotEmpty(),
        ]);
    }

    /**
     * Двигатели поколения — только если им подходят разные аккумуляторы.
     * Иначе выбор мотора ничего не меняет и шаг не нужен.
     *
     * @return Collection<int, string>
     */
    public function engines(string $brand, string $model, string $generation): Collection
    {
        $engines = BatteryFitment::query()
            ->where('brand', $brand)
            ->where('model', $model)
            ->where('generation', $generation)
            ->whereNotNull('engine')
            ->get();

        // Сравниваем не цифры в данных («55, 60, 62 Ач» и «60, 62, 65 Ач»),
        // а условия подбора: одинаковые условия — одинаковые аккумуляторы.
        $variants = $engines->map(fn (BatteryFitment $fitment): string => json_encode([
            collect($fitment->matchingAttributeValueIds())->map(fn (?array $ids): ?array => $ids === null ? null : collect($ids)->sort()->values()->all())->all(),
            $fitment->terminalValueIds(),
        ]))->unique();

        return $variants->count() > 1
            ? $engines->pluck('engine')->unique()->sort(SORT_NATURAL)->values()
            : collect();
    }

    /**
     * Запись машины. Без двигателя — общая запись поколения (если она есть),
     * иначе первая.
     */
    public function findFitment(string $brand, string $model, string $generation, string $engine = ''): ?BatteryFitment
    {
        $generation = trim($generation);
        $engine = trim($engine);

        return BatteryFitment::query()
            ->where('brand', $brand)
            ->where('model', $model)
            ->when(
                $generation === '' || $generation === self::DEFAULT_GENERATION_LABEL,
                fn ($query) => $query->where(fn ($query) => $query->whereNull('generation')->orWhere('generation', '')),
                fn ($query) => $query->where('generation', $generation),
            )
            ->when(
                $engine === '',
                fn ($query) => $query->orderByRaw('engine is null desc'),
                fn ($query) => $query->where('engine', $engine),
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

    public function brandLogo(string $brand): string
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
