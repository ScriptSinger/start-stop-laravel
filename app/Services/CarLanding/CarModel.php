<?php

namespace App\Services\CarLanding;

use App\Models\BatteryFitment;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Модель машины для посадочной страницы: все записи подбора этой модели
 * (поколения, моторы) с данными для подбора.
 */
final readonly class CarModel
{
    use DescribesFitments;

    /**
     * @param  Collection<int, BatteryFitment>  $fitments
     */
    public function __construct(
        public CarBrand $brand,
        public string $slug,
        public string $name,
        public Collection $fitments,
    ) {}

    /**
     * «Lada Granta».
     */
    public function fullName(): string
    {
        return $this->brand->name.' '.$this->name;
    }

    public function url(): string
    {
        return route('car-landing.model', [$this->brand->slug, $this->slug]);
    }

    public function key(): string
    {
        return $this->brand->slug.'/'.$this->slug;
    }

    /**
     * Поколения со своими записями. Записи без поколения относятся ко всей
     * модели и сюда не входят. Одно поколение — не повод для отдельной
     * страницы: его покажет страница модели.
     *
     * @return Collection<int, CarGeneration>
     */
    public function generations(): Collection
    {
        $generations = $this->fitments
            ->filter(fn (BatteryFitment $fitment): bool => $this->generationName($fitment) !== null)
            ->groupBy(fn (BatteryFitment $fitment): string => $this->generationName($fitment))
            ->map(fn (Collection $fitments, string $label): CarGeneration => new CarGeneration($this, Str::slug($label), $label, $fitments->values()))
            // По году начала выпуска («I 2015 - 2022» раньше «I Рестайлинг 2022 - н.в.»),
            // подписи без года (моторы) — по алфавиту после них.
            ->sortBy([
                fn (CarGeneration $a, CarGeneration $b): int => $this->startYear($a->label) <=> $this->startYear($b->label),
                fn (CarGeneration $a, CarGeneration $b): int => strnatcasecmp($a->label, $b->label),
            ])
            ->values();

        return $generations->count() > 1 ? $generations : collect();
    }

    /**
     * Поколение без марки и модели в начале: «ВАЗ (Lada) Vesta I 2015 - 2022» → «I 2015 - 2022».
     */
    public function generationLabel(BatteryFitment $fitment): string
    {
        return $this->generationName($fitment) ?? 'Все годы';
    }

    /**
     * Строки таблицы «по годам»: одно поколение в данных часто записано
     * несколько раз (под разные моторы) — сводим в одну строку, объединяя
     * ёмкости и габариты.
     *
     * @return Collection<int, array{generation: string, capacity: string, polarity: string, dimensions: list<string>}>
     */
    public function generationRows(): Collection
    {
        return $this->fitments
            ->groupBy(fn (BatteryFitment $fitment): string => $this->generationLabel($fitment))
            ->map(fn (Collection $fitments, string $generation): array => [
                'generation' => $generation,
                'capacity' => $fitments->flatMap(fn (BatteryFitment $fitment): array => $fitment->capacities())->unique()->sort()->implode(', ') ?: '—',
                'polarity' => $fitments->map(fn (BatteryFitment $fitment): string => trim((string) $fitment->polarity))->filter()->unique()->implode(', ') ?: '—',
                'dimensions' => $fitments
                    ->flatMap(fn (BatteryFitment $fitment): array => $fitment->dimensions())
                    ->map(fn (array $dims): string => implode('×', $dims))
                    ->unique()
                    ->values()
                    ->all(),
            ])
            ->values();
    }

    private function generationName(BatteryFitment $fitment): ?string
    {
        $generation = trim((string) $fitment->generation);

        foreach ([$this->brand->fitmentBrand.' '.$this->name, $this->name] as $prefix) {
            if (str_starts_with(mb_strtolower($generation), mb_strtolower($prefix))) {
                $generation = trim(mb_substr($generation, mb_strlen($prefix)));
            }
        }

        return $generation === '' ? null : $generation;
    }

    private function startYear(string $label): int
    {
        return preg_match('/\b(19|20)\d{2}\b/', $label, $matches) ? (int) $matches[0] : PHP_INT_MAX;
    }
}
