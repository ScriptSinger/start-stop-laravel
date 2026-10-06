<?php

namespace App\Console\Commands;

use App\Models\BatteryFitment;
use App\Services\BatteryFitments\PodborRow;
use App\Services\Import\XlsxReader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('battery:import-fitments {file : Выгрузка подбора .xlsx (podbor.xlsx)} {--force : Записать в базу; без флага — только отчёт}')]
#[Description('Загрузить базу подбора АКБ (машина → ёмкость, полярность, габариты, клеммы) из выгрузки парсера')]
class ImportBatteryFitments extends Command
{
    /**
     * Без --force ничего не пишет — показывает, что изменится. С --force
     * заменяет таблицу целиком в одной транзакции и пересчитывает
     * посадочные страницы. Перед запуском: php artisan db:backup.
     */
    public function handle(XlsxReader $reader): int
    {
        $path = $this->argument('file');

        if (! is_file($path)) {
            $this->error("Файл {$path} не найден.");

            return self::FAILURE;
        }

        [$rows, $skipped] = $this->read($reader, $path);

        $this->report($rows, $skipped);

        if (! $this->option('force')) {
            $this->warn('Пробный прогон: база не изменена. Для записи добавьте --force.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows): void {
            BatteryFitment::query()->delete();

            $rows->map(fn (array $row): array => [...$row, 'created_at' => now(), 'updated_at' => now()])
                ->chunk(500)
                ->each(fn (Collection $chunk) => BatteryFitment::query()->insert($chunk->values()->all()));
        });

        $this->info("Записано машин: {$rows->count()}.");
        $this->call('car-landings:refresh');

        return self::SUCCESS;
    }

    /**
     * @return array{Collection<int, array<string, string|null>>, int}
     */
    private function read(XlsxReader $reader, string $path): array
    {
        $rows = collect();
        $skipped = 0;

        foreach ($reader->rows($path) as $index => $cells) {
            if ($index === 0) {
                continue; // Заголовки колонок.
            }

            $row = PodborRow::parse($cells);

            if ($row === null) {
                $skipped++;

                continue;
            }

            $rows->push($row);
        }

        return [$rows, $skipped];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function report(Collection &$rows, int $skipped): void
    {
        $fixes = $rows->flatMap(fn (array $row): array => $row['fixes'])->countBy();
        $rows = $rows->map(fn (array $row): array => array_diff_key($row, ['fixes' => true]));

        $current = BatteryFitment::query()->count();
        $engineGenerations = $rows->whereNotNull('engine')
            ->groupBy(fn (array $row): string => $row['brand'].'|'.$row['model'].'|'.$row['generation'])
            ->filter(fn (Collection $engines): bool => $engines
                ->map(fn (array $row): string => $row['capacity'].'|'.$row['polarity'].'|'.$row['dims'].'|'.$row['terminals'])
                ->unique()
                ->count() > 1);

        $this->table(['', 'Строк'], [
            ['Сейчас в базе', $current],
            ['В файле (без пустых)', $rows->count()],
            ['Пропущено пустых строк', $skipped],
            ['С двигателем', $rows->whereNotNull('engine')->count()],
            ['Поколений, где двигатели различаются по АКБ', $engineGenerations->count()],
            ['С несколькими вариантами полярности', $rows->filter(fn (array $row): bool => str_contains((string) $row['polarity'], ','))->count()],
            ['С типом клемм', $rows->whereNotNull('terminals')->count()],
            ['Исправлено: модель-дата Excel (Saab 9-3, 9-5)', $fixes->get('excel-date', 0)],
            ['Исправлено: сломанная ячейка марки', $fixes->get('brand', 0)],
        ]);

        $this->line('Типы клемм: '.$rows->countBy(fn (array $row): string => $row['terminals'] ?? 'не указан')->map(fn (int $count, string $type): string => "{$type} {$count}")->implode(', '));
    }
}
