<?php

namespace App\Console\Commands;

use App\Models\TradeInType;
use App\Services\Catalog\TradeInTypes;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('trade-in:fill-types {--force : Записать; без флага — только отчёт}')]
#[Description('Загрузить типы корпусов и скидки за сдачу старого АКБ (диапазоны размеров ±2 мм)')]
class FillTradeInTypes extends Command
{
    /**
     * Только добавляет: типы, которых нет по коду, создаются; существующие, их суммы и
     * диапазоны не меняются. Типы товарам не назначает: для этого trade-in:assign-types.
     * Перед запуском: php artisan db:backup.
     */
    public function handle(): int
    {
        $existing = TradeInType::query()->ordered()->get();
        $toCreate = $this->rowsToCreate($existing);

        $this->components->twoColumnDetail('Типов корпусов в базе', (string) $existing->count());
        $this->components->twoColumnDetail('Будет создано', (string) $toCreate->count());

        foreach ($toCreate as $row) {
            $this->line("Новый тип: {$row['name']} — ".($row['amount'] === null ? 'без суммы' : "{$row['amount']} руб."));
        }

        if (! $this->option('force')) {
            $this->warn('Пробный прогон: ничего не записано. Для записи добавьте --force.');

            return self::SUCCESS;
        }

        DB::transaction(fn () => $toCreate->each(fn (array $row): TradeInType => TradeInType::query()->create($row)));

        $this->info("Готово: создано типов — {$toCreate->count()}. Типы товарам назначает trade-in:assign-types.");

        return self::SUCCESS;
    }

    /**
     * Типы стартовой таблицы, которых ещё нет в базе (по коду).
     *
     * @param  Collection<int, TradeInType>  $existing
     * @return Collection<int, array<string, mixed>>
     */
    private function rowsToCreate(Collection $existing): Collection
    {
        $tolerance = TradeInTypes::TOLERANCE;

        return collect(TradeInTypes::rows())
            ->reject(fn (array $row): bool => $existing->contains('code', $row['code']))
            ->map(function (array $row, int $index) use ($tolerance): array {
                [$length, $width, $height] = $row['dimensions'] ?? [null, null, null];

                return [
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'amount' => $row['amount'],
                    'length_min' => $length === null ? null : $length - $tolerance,
                    'length_max' => $length === null ? null : $length + $tolerance,
                    'width_min' => $width === null ? null : $width - $tolerance,
                    'width_max' => $width === null ? null : $width + $tolerance,
                    'height_min' => $height === null ? null : $height - $tolerance,
                    'height_max' => $height === null ? null : $height + $tolerance,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ];
            })
            ->values();
    }
}
