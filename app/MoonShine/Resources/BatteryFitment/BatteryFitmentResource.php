<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\BatteryFitment;

use App\Models\BatteryFitment;
use App\MoonShine\Handlers\SafeImportHandler;
use App\MoonShine\Resources\BatteryFitment\Pages\BatteryFitmentDetailPage;
use App\MoonShine\Resources\BatteryFitment\Pages\BatteryFitmentFormPage;
use App\MoonShine\Resources\BatteryFitment\Pages\BatteryFitmentIndexPage;
use App\MoonShine\Resources\Concerns\ResetsPageOutOfRange;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\Handlers\Handler;
use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\ExportHandler;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use RuntimeException;

/**
 * @extends ModelResource<BatteryFitment, BatteryFitmentIndexPage, BatteryFitmentFormPage, BatteryFitmentDetailPage>
 */
class BatteryFitmentResource extends ModelResource implements HasImportExportContract
{
    use ImportExportConcern;
    use ResetsPageOutOfRange;

    protected string $model = BatteryFitment::class;

    protected string $title = 'База АКБ по авто';

    protected string $column = 'brand';

    // По алфавиту: марка → модель → поколение, а не в порядке загрузки.
    protected string $sortColumn = 'brand';

    protected SortDirection $sortDirection = SortDirection::ASC;

    /**
     * @return string[]
     */
    protected function search(): array
    {
        return ['brand', 'model', 'generation', 'engine'];
    }

    /**
     * Выгрузка — с текущими фильтрами и сортировкой списка.
     */
    protected function export(): ?Handler
    {
        return ExportHandler::make('Экспорт в Excel');
    }

    protected function import(): ?Handler
    {
        return SafeImportHandler::make('Импорт из Excel')
            ->hint('Файл — выгрузка «Экспорт в Excel» с вашими правками. Строка с ID правит запись, строка без ID добавляет новую машину.');
    }

    /**
     * @return list<FieldContract>
     */
    protected function exportFields(): iterable
    {
        return [
            ...$this->columnFields(),
            // Подпись вместо кода: в Excel правят «Узкие (азиатские)», а не «thin».
            Text::make('Клеммы', 'terminals')
                ->modifyRawValue(fn (mixed $code): string => BatteryFitment::terminalLabels()[$code] ?? ''),
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function importFields(): iterable
    {
        return [
            ...$this->columnFields(),
            // Подпись переводится обратно в код в beforeImportFilling().
            Text::make('Клеммы', 'terminals')->nullable(),
        ];
    }

    /**
     * @return list<FieldContract>
     */
    private function columnFields(): array
    {
        return [
            ID::make(),
            Text::make('Марка', 'brand'),
            Text::make('Модель', 'model'),
            Text::make('Поколение', 'generation')->nullable(),
            Text::make('Двигатель', 'engine')->nullable(),
            Text::make('Ёмкость', 'capacity')->nullable(),
            Text::make('Полярность', 'polarity')->nullable(),
            Text::make('Габариты', 'dims')->nullable(),
        ];
    }

    /**
     * Строка с ID правит запись, без ID — добавляет машину. Чужой ID,
     * строка без марки или модели и неизвестный тип клемм останавливают
     * загрузку целиком (она идёт одной транзакцией).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function beforeImportFilling(array $data): array
    {
        if (filled($data['id'] ?? null) && ! BatteryFitment::query()->whereKey($data['id'])->exists()) {
            throw new RuntimeException("записи с ID {$data['id']} нет в базе");
        }

        if (blank($data['brand'] ?? null) || blank($data['model'] ?? null)) {
            throw new RuntimeException('в файле есть строка без марки или модели');
        }

        if (array_key_exists('terminals', $data) && filled($data['terminals'])) {
            $labels = BatteryFitment::terminalLabels();
            $code = isset($labels[$data['terminals']]) ? $data['terminals'] : array_search($data['terminals'], $labels, true);

            $data['terminals'] = $code !== false ? $code : throw new RuntimeException("неизвестный тип клемм «{$data['terminals']}», допустимо: ".implode(', ', $labels));
        }

        return $data;
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            BatteryFitmentIndexPage::class,
            BatteryFitmentFormPage::class,
            BatteryFitmentDetailPage::class,
        ];
    }
}
