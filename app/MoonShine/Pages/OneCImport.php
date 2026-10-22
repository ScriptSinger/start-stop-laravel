<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\MoonShine\Resources\Product\ProductResource;
use App\Services\Backup\DatabaseBackup;
use App\Services\Import\OneCImportFiles;
use App\Services\Import\OneCImportReport;
use App\Services\Import\OneCPriceImporter;
use Illuminate\Http\Request;
use InvalidArgumentException;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Http\Responses\MoonShineJsonResponse;
use MoonShine\Laravel\Pages\Page;
use MoonShine\MenuManager\Attributes\SkipMenu;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Link;
use MoonShine\UI\Components\Metrics\Wrapped\ValueMetric;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\File;
use MoonShine\UI\Fields\Text;
use RuntimeException;

/**
 * Загрузка прайса из 1С вручную (на время отладки): файл загружается, показывается
 * отчёт — что изменится и каких товаров нет на сайте, — и по кнопке изменения
 * применяются. Перед применением снимается копия базы.
 */
#[SkipMenu]
class OneCImport extends Page
{
    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            '#' => $this->getTitle(),
        ];
    }

    public function getTitle(): string
    {
        return $this->title ?: 'Загрузка из 1С';
    }

    /**
     * Загрузить файл и открыть отчёт по нему.
     */
    #[AsyncMethod]
    public function upload(Request $request, OneCImportFiles $files, OneCPriceImporter $importer): MoonShineJsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:20480']]);

        $id = $files->store($request->file('file'));

        try {
            $importer->preview((string) $files->path($id));
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $files->delete($id);

            return MoonShineJsonResponse::make()->toast($exception->getMessage(), ToastType::ERROR);
        }

        return MoonShineJsonResponse::make()
            ->toast('Файл загружен: отчёт ниже. Данные пока не изменены.', ToastType::SUCCESS)
            ->redirect($this->getRoute(['import' => $id]));
    }

    /**
     * Применить изменения из загруженного файла: копия базы, затем запись.
     */
    #[AsyncMethod]
    public function apply(Request $request, OneCImportFiles $files, OneCPriceImporter $importer, DatabaseBackup $backup): MoonShineJsonResponse
    {
        $id = (string) $request->input('import');
        $path = $files->path($id);

        if ($path === null) {
            return MoonShineJsonResponse::make()->toast('Загрузка не найдена: загрузите файл заново.', ToastType::ERROR);
        }

        try {
            $backup->create('before-onec-import');
        } catch (RuntimeException $exception) {
            return MoonShineJsonResponse::make()->toast('Не применено: не удалось снять копию базы. '.$exception->getMessage(), ToastType::ERROR);
        }

        try {
            $changed = $importer->apply($importer->preview($path));
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return MoonShineJsonResponse::make()->toast($exception->getMessage(), ToastType::ERROR);
        }

        $files->markApplied($id, $changed);

        return MoonShineJsonResponse::make()
            ->toast("Применено: обновлено товаров — {$changed}. Копия базы снята перед записью.", ToastType::SUCCESS)
            ->redirect($this->getRoute(['import' => $id]));
    }

    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        $files = app(OneCImportFiles::class);
        $id = (string) request()->query('import', '');
        $path = $id !== '' ? $files->path($id) : null;

        return [
            $this->uploadForm(),
            ...($path !== null ? $this->report($id, $files, app(OneCPriceImporter::class), $path) : []),
            $this->recentUploads($files),
        ];
    }

    private function uploadForm(): Box
    {
        return Box::make('Загрузить прайс', [
            Heading::make('Файл из 1С: CSV с колонками «Код; Артикул; Наименование; Свободный остаток; Розничная цена». Сначала покажем, что изменится: данные меняются только после кнопки «Применить».', h: 6),
            FormBuilder::make()
                ->asyncMethod('upload')
                ->fields([File::make('Файл прайса (CSV)', 'file')->required()])
                ->submit('Загрузить и проверить'),
        ]);
    }

    /**
     * @return list<ComponentContract>
     */
    private function report(string $id, OneCImportFiles $files, OneCPriceImporter $importer, string $path): array
    {
        try {
            $report = $importer->preview($path);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return [Box::make('Отчёт', [Heading::make($exception->getMessage(), h: 6)])];
        }

        $meta = $files->meta($id);

        return [
            Grid::make([
                $this->metric('Строк в файле', $report->rows),
                $this->metric('Найдено на сайте', $report->found),
                $this->metric('Нет на сайте', $report->notFound->count()),
                $this->metric('Без цены в файле', $report->withoutPrice),
                $this->metric('Изменится цена', $report->priceChanges()),
                $this->metric('Изменится остаток', $report->quantityChanges()),
            ]),

            Box::make('Отчёт по файлу «'.($meta['original_name'] ?? $id).'»', [
                Heading::make($this->status($meta, $report), h: 6),
                Heading::make('Правила: товар ищется по коду 1С; меняются только цена и остаток; пустая цена — цена не меняется; пустой остаток — 0; товары, которых нет в файле, и строки без товара на сайте не трогаются.', h: 6),
                ActionButton::make('Применить к базе')
                    ->method('apply', params: ['import' => $id])
                    ->withConfirm(
                        title: 'Применить изменения?',
                        content: "Будет снята копия базы, затем обновлены цена и остаток у товаров: {$report->changes->count()}.",
                        button: 'Применить',
                    )
                    ->primary()
                    ->icon('check'),
            ]),

            Box::make("Что изменится ({$report->changes->count()})", [$this->changesTable($report)]),
            Box::make("Нет на сайте ({$report->notFound->count()})", [
                Heading::make('Строки файла, для которых на сайте нет товара с таким кодом 1С. Товары не создаются.', h: 6),
                $this->notFoundTable($report),
            ]),
        ];
    }

    /**
     * @param  array{original_name?: string, uploaded_at?: string, applied_at?: string, applied_count?: int}  $meta
     */
    private function status(array $meta, OneCImportReport $report): string
    {
        if (isset($meta['applied_at'])) {
            return 'Применено '.date('d.m.Y H:i', (int) strtotime($meta['applied_at'])).': обновлено товаров — '.($meta['applied_count'] ?? 0).'. Если нажать «Применить» снова, изменятся только товары, которые с тех пор разошлись с файлом.';
        }

        return $report->changes->isEmpty() ? 'Изменений нет: цены и остатки на сайте уже совпадают с файлом.' : 'Не применено: данные на сайте пока не изменены.';
    }

    private function changesTable(OneCImportReport $report): TableBuilder
    {
        $products = app(ProductResource::class);

        return TableBuilder::make(items: $report->changes->map(fn (array $change): array => [
            'code' => (string) $change['product']->external_id,
            'id' => $change['product']->getKey(),
            'name' => $change['product']->name,
            'price' => $change['price'] ? $this->money($change['price'][0]).' → '.$this->money($change['price'][1]) : '',
            'quantity' => $change['quantity'] ? "{$change['quantity'][0]} → {$change['quantity'][1]}" : '',
            'notes' => implode('; ', $change['notes']),
        ])->all())
            ->fields([
                Text::make('Код 1С', 'code'),
                Text::make('Название на сайте', 'name', fn (array $row): string => (string) Link::make($products->getDetailPageUrl($row['id']), $row['name'])->render())->unescape(),
                Text::make('Цена', 'price'),
                Text::make('Остаток', 'quantity'),
                Text::make('Обратить внимание', 'notes'),
            ])
            ->withNotFound();
    }

    private function notFoundTable(OneCImportReport $report): TableBuilder
    {
        return TableBuilder::make(items: $report->notFound->values()->map(fn (array $row): array => [
            'code' => $row['external_id'],
            'name' => $row['name'],
            'price' => $row['price'] !== null ? $this->money($row['price']) : '',
            'quantity' => $row['quantity'] !== null ? (string) (int) round($row['quantity']) : '',
        ])->all())
            ->fields([
                Text::make('Код 1С', 'code'),
                Text::make('Название в файле', 'name'),
                Text::make('Цена', 'price'),
                Text::make('Остаток', 'quantity'),
            ])
            ->withNotFound();
    }

    private function recentUploads(OneCImportFiles $files): Box
    {
        return Box::make('Последние загрузки', [
            TableBuilder::make(items: $files->recent()->map(fn (array $upload): array => [
                'id' => $upload['id'],
                'name' => $upload['original_name'],
                'uploaded' => $upload['uploaded_at']?->format('d.m.Y H:i') ?? '',
                'status' => $upload['applied_at'] ? 'применено '.$upload['applied_at']->format('d.m.Y H:i').' ('.$upload['applied_count'].')' : 'не применено',
            ])->all())
                ->fields([
                    Text::make('Дата', 'uploaded'),
                    Text::make('Файл', 'name', fn (array $row): string => (string) Link::make($this->getRoute(['import' => $row['id']]), $row['name'])->render())->unescape(),
                    Text::make('Состояние', 'status'),
                ])
                ->withNotFound(),
        ]);
    }

    private function metric(string $label, int $value): Column
    {
        return Column::make([
            ValueMetric::make($label)->value($value),
        ], colSpan: 2, adaptiveColSpan: 6);
    }

    private function money(float $value): string
    {
        return number_format($value, $value === floor($value) ? 0 : 2, ',', ' ').' ₽';
    }
}
