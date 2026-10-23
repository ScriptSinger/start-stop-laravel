<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Models\Supplier;
use App\MoonShine\Resources\Supplier\SupplierResource;
use App\Services\Backup\DatabaseBackup;
use App\Services\Supplier\SupplierImportFiles;
use App\Services\Supplier\SupplierImportReport;
use App\Services\Supplier\SupplierPriceImporter;
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
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use RuntimeException;

/**
 * Загрузка прайса поставщика вручную: файл загружается, показывается отчёт
 * (пробный прогон — в базу ничего не пишется), и по кнопке изменения
 * применяются. Перед применением снимается копия базы.
 */
#[SkipMenu]
class SupplierImport extends Page
{
    private const MAX_ROWS_SHOWN = 200;

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
        return $this->title ?: 'Прайсы поставщиков';
    }

    /**
     * Загрузить файл и открыть отчёт по нему.
     */
    #[AsyncMethod]
    public function upload(Request $request, SupplierImportFiles $files, SupplierPriceImporter $importer): MoonShineJsonResponse
    {
        $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:20480'],
        ], [
            'file.mimes' => 'Нужен файл .xlsx или .csv. Старый .xls пересохраните в Excel/Calc как .xlsx.',
        ]);

        $supplier = Supplier::query()->findOrFail($request->integer('supplier_id'));
        $id = $files->store($request->file('file'), $supplier->getKey());

        try {
            $importer->run($supplier, (string) $files->path($id), apply: false);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $files->delete($id);

            return MoonShineJsonResponse::make()->toast($exception->getMessage(), ToastType::ERROR);
        }

        return MoonShineJsonResponse::make()
            ->toast('Файл загружен: отчёт ниже. Данные пока не изменены.', ToastType::SUCCESS)
            ->redirect($this->getRoute(['import' => $id]));
    }

    /**
     * Применить загруженный прайс: копия базы, затем запись.
     */
    #[AsyncMethod]
    public function apply(Request $request, SupplierImportFiles $files, SupplierPriceImporter $importer, DatabaseBackup $backup): MoonShineJsonResponse
    {
        $id = (string) $request->input('import');
        $path = $files->path($id);
        $supplier = Supplier::query()->find($files->meta($id)['supplier_id'] ?? 0);

        if ($path === null || $supplier === null) {
            return MoonShineJsonResponse::make()->toast('Загрузка не найдена: загрузите файл заново.', ToastType::ERROR);
        }

        try {
            $backup->create('before-supplier-import');
        } catch (RuntimeException $exception) {
            return MoonShineJsonResponse::make()->toast('Не применено: не удалось снять копию базы. '.$exception->getMessage(), ToastType::ERROR);
        }

        try {
            $report = $importer->run($supplier, $path, apply: true);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return MoonShineJsonResponse::make()->toast($exception->getMessage(), ToastType::ERROR);
        }

        $files->markApplied($id, ['created' => $report->created, 'on_order' => $report->productsOnOrder, 'cleared' => $report->productsCleared]);

        return MoonShineJsonResponse::make()
            ->toast("Применено: товаров под заказ — {$report->productsOnOrder}. Копия базы снята перед записью.", ToastType::SUCCESS)
            ->redirect($this->getRoute(['import' => $id]));
    }

    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        $files = app(SupplierImportFiles::class);
        $id = (string) request()->query('import', '');
        $path = $id !== '' ? $files->path($id) : null;

        return [
            $this->uploadForm(),
            ...($path !== null ? $this->report($id, $files, $path) : []),
            $this->recentUploads($files),
        ];
    }

    private function uploadForm(): Box
    {
        return Box::make('Загрузить прайс', [
            Heading::make('Файл .xlsx или .csv поставщика. Сначала покажем, что изменится: данные меняются только после кнопки «Применить». Старый .xls пересохраните как .xlsx.', h: 6),
            FormBuilder::make()
                ->asyncMethod('upload')
                ->fields([
                    Select::make('Поставщик', 'supplier_id')
                        ->options(Supplier::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->required(),
                    File::make('Файл прайса', 'file')->required(),
                ])
                ->submit('Загрузить и проверить'),
        ]);
    }

    /**
     * @return list<ComponentContract>
     */
    private function report(string $id, SupplierImportFiles $files, string $path): array
    {
        $meta = $files->meta($id);
        $supplier = Supplier::query()->find($meta['supplier_id'] ?? 0);

        if ($supplier === null) {
            return [Box::make('Отчёт', [Heading::make('Поставщик этой загрузки удалён.', h: 6)])];
        }

        try {
            $report = app(SupplierPriceImporter::class)->run($supplier, $path, apply: false);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return [Box::make('Отчёт', [Heading::make($exception->getMessage(), h: 6)])];
        }

        return [
            Grid::make([
                $this->metric('Строк в прайсе', $report->rows),
                $this->metric('Новых позиций', $report->created),
                $this->metric('Изменилась закупка', $report->costChanged),
                $this->metric('Пропало из прайса', $report->vanished),
                $this->metric('Под заказ станет', $report->productsOnOrder),
                $this->metric('Без товара на сайте', $report->unlinked->count()),
            ]),

            Box::make('Отчёт по файлу «'.($meta['original_name'] ?? $id).'» — '.$supplier->name, [
                Heading::make($this->status($meta, $report), h: 6),
                Heading::make('Правила: цена на сайте = закупка + наценка поставщика, округление вверх до 50 ₽; под заказ — если у поставщика не меньше порога остатка (или стоит «не учитывать количество»); при остатке в 1С действуют цена и остаток 1С; позиции, которых нет в новом прайсе, снимаются с продажи.', h: 6),
                ActionButton::make('Применить к базе')
                    ->method('apply', params: ['import' => $id])
                    ->withConfirm(
                        title: 'Применить прайс?',
                        content: 'Будет снята копия базы, затем обновлены предложения поставщика и «под заказ» у товаров.',
                        button: 'Применить',
                    )
                    ->primary()
                    ->icon('check'),
            ]),

            Box::make("Изменилась закупка ({$report->costChanges->count()})", [$this->costChangesTable($report)]),
            Box::make("Без товара на сайте ({$report->unlinked->count()})", [
                Heading::make('Позиции прайса, для которых нет товара на сайте. Они сохраняются как предложения и ждут привязки или создания товара. Показаны первые '.self::MAX_ROWS_SHOWN.'.', h: 6),
                $this->unlinkedTable($report),
            ]),
        ];
    }

    /**
     * @param  array{supplier_id?: int, extension?: string, original_name?: string, uploaded_at?: string, applied_at?: string, summary?: array<string, int>}  $meta
     */
    private function status(array $meta, SupplierImportReport $report): string
    {
        if (isset($meta['applied_at'])) {
            return 'Применено '.date('d.m.Y H:i', (int) strtotime($meta['applied_at'])).'. Если нажать «Применить» снова, изменятся только позиции, которые с тех пор разошлись с файлом.';
        }

        return 'Не применено: данные на сайте пока не изменены. После применения под заказ: '.$report->productsOnOrder.', снимется с «под заказ»: '.$report->productsCleared.'.';
    }

    private function costChangesTable(SupplierImportReport $report): TableBuilder
    {
        return TableBuilder::make(items: $report->costChanges->take(self::MAX_ROWS_SHOWN)->map(fn (array $change): array => [
            'name' => $change['name'],
            'cost' => $this->money($change['old']).' → '.$this->money($change['new']),
        ])->all())
            ->fields([
                Text::make('Позиция', 'name'),
                Text::make('Закупка', 'cost'),
            ])
            ->withNotFound();
    }

    private function unlinkedTable(SupplierImportReport $report): TableBuilder
    {
        return TableBuilder::make(items: $report->unlinked->take(self::MAX_ROWS_SHOWN)->map(fn (array $row): array => [
            'code' => $row['code'],
            'name' => $row['name'],
            'cost' => $this->money($row['cost']),
        ])->all())
            ->fields([
                Text::make('Код', 'code'),
                Text::make('Название в прайсе', 'name'),
                Text::make('Закупка', 'cost'),
            ])
            ->withNotFound();
    }

    private function recentUploads(SupplierImportFiles $files): Box
    {
        $suppliers = Supplier::query()->pluck('name', 'id');
        $supplierPage = app(SupplierResource::class)->getIndexPageUrl();

        return Box::make('Последние загрузки', [
            Link::make($supplierPage, 'Поставщики и их настройки'),
            TableBuilder::make(items: $files->recent()->map(fn (array $upload): array => [
                'id' => $upload['id'],
                'name' => $upload['original_name'],
                'supplier' => (string) $suppliers->get($upload['supplier_id'], ''),
                'uploaded' => $upload['uploaded_at']?->format('d.m.Y H:i') ?? '',
                'status' => $upload['applied_at'] ? 'применено '.$upload['applied_at']->format('d.m.Y H:i') : 'не применено',
            ])->all())
                ->fields([
                    Text::make('Дата', 'uploaded'),
                    Text::make('Поставщик', 'supplier'),
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
