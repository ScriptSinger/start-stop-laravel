<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Models\Product;
use App\MoonShine\Resources\Product\ProductResource;
use App\Services\Import\OneCImportFiles;
use App\Services\Import\OneCImportReport;
use App\Services\Import\OneCModeration;
use App\Services\Import\OneCPriceImporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\ModalContract;
use MoonShine\Laravel\Http\Responses\MoonShineJsonResponse;
use MoonShine\Laravel\Pages\Page;
use MoonShine\MenuManager\Attributes\SkipMenu;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\HttpMethod;
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
use MoonShine\UI\Fields\Hidden;
use MoonShine\UI\Fields\Select;
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
    public function apply(Request $request, OneCModeration $service): MoonShineJsonResponse
    {
        if (! $request->isMethod('POST')) {
            return MoonShineJsonResponse::make()->setStatusCode(405);
        }
        $id = (string) $request->input('import');
        $admin = $request->user(config('moonshine.auth.guard'));
        try {
            $count = $service->apply($id, (int) $admin->getAuthIdentifier(), $admin->name);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return MoonShineJsonResponse::make()->toast($exception->getMessage(), ToastType::ERROR);
        }

        return MoonShineJsonResponse::make()->toast('Применено: обновлено товаров — '.$count, ToastType::SUCCESS)->redirect($this->getRoute(['import' => $id]));
    }

    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        $files = app(OneCImportFiles::class);
        $id = (string) request()->query('import', '');
        $path = $id !== '' ? $files->path($id) : null;

        if ($id !== '') {
            return [Link::make($this->getRoute(), '← К загрузкам прайсов')->class('mb-4'),
                ...($path !== null ? $this->report($id, $files, app(OneCPriceImporter::class), $path) : [Box::make('Отчёт', [Heading::make('Загрузка не найдена.', h: 6)])])];
        }

        return [$this->uploadForm(), $this->recentUploads($files)];
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
            Box::make('Отчёт по файлу «'.($meta['original_name'] ?? $id).'»', [
                Heading::make($this->status($meta, $report), h: 6),
                Heading::make('Правила: товар ищется по коду 1С; меняются только цена и остаток; пустая цена — цена не меняется; пустой остаток — 0; товары, которых нет в файле, и строки без товара на сайте не трогаются.', h: 6),
                ...(isset($meta['applied_at']) || $report->rows === 0 ? [] : [ActionButton::make('Применить к базе')
                    ->method('apply', params: ['import' => $id])
                    ->withConfirm(
                        title: 'Применить изменения?',
                        content: "Будет снята копия базы, затем обновлены цена и остаток у товаров: {$report->changes->count()}.",
                        button: 'Применить',
                    )
                    ->primary()
                    ->icon('check')]),
            ]),

            Grid::make([
                $this->metric('Строк в файле', $report->rows),
                $this->metric('Найдено на сайте', $report->found),
                $this->metric('Нет на сайте', $report->notFound->count()),
                $this->metric('Без цены в файле', $report->withoutPrice),
                $this->metric('Изменится цена', $report->priceChanges()),
                $this->metric('Изменится остаток', $report->quantityChanges()),
            ]),

            Box::make("Что изменится ({$report->changes->count()})", [$this->changesTable($report)]),
            ...$this->draftBox($id),
            Box::make("Нет на сайте ({$report->notFound->count()})", [
                Heading::make('Строки файла, для которых на сайте нет товара с таким кодом 1С. Товары не создаются.', h: 6),
                $this->notFoundTable($report, $id, isset($meta['applied_at'])),
                ...$this->pagination($id, $report->notFound->count()),
            ]),
        ];
    }

    /**
     * @param  array{original_name?: string, uploaded_at?: string, applied_at?: string, applied_count?: int}  $meta
     */
    private function status(array $meta, OneCImportReport $report): string
    {
        if (isset($meta['applied_at'])) {
            return 'Применено '.date('d.m.Y H:i', (int) strtotime($meta['applied_at'])).': обновлено товаров — '.($meta['applied_count'] ?? 0).'. Показатели ниже рассчитаны относительно текущей базы.';
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
                Text::make('Название на сайте', 'name', fn (array $row): string => $this->productModal((int) $row['id'], $row['name'], 'changes'))->unescape(),
                Text::make('Цена', 'price'),
                Text::make('Остаток', 'quantity'),
                Text::make('Обратить внимание', 'notes'),
            ])
            ->withNotFound();
    }

    private function notFoundTable(OneCImportReport $report, string $id, bool $applied): TableBuilder
    {
        $selectedCodes = app(OneCModeration::class)->drafts($id, $this->adminId())->pluck('code')->flip();

        return TableBuilder::make(items: $report->notFound->values()->forPage($this->pageNumber($report->notFound->count()), 200)->map(fn (array $row): array => [
            'selected' => $selectedCodes->has($row['external_id']),
            'code' => $row['external_id'],
            'name' => $row['name'],
            'price' => $row['price'] !== null ? $this->money($row['price']) : '',
            'quantity' => $row['quantity'] !== null ? (string) (int) round($row['quantity']) : '',
        ])->all())
            ->fields([
                Text::make('Код 1С', 'code'),
                Text::make('Название в файле', 'name', fn (array $row): string => $this->rowModal($row, 'unlinked'))->unescape(),
                Text::make('Цена', 'price'),
                Text::make('Остаток', 'quantity'),
                Text::make('Товар на сайте', 'selection', fn (array $row): string => $applied ? $this->selectionButton($id, $row) : 'Сначала примените прайс')->unescape(),
            ])->customAttributes(['data-wrap-text' => ''])
            ->withNotFound();
    }

    #[AsyncMethod]
    public function searchProducts(Request $request): JsonResponse
    {
        $data = $request->validate(['query' => ['nullable', 'string', 'max:150']]);
        $search = trim($data['query'] ?? '');
        if ($search === '') {
            return response()->json(['options' => []]);
        }

        $products = Product::query()->with('attributeValues')
            ->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', $search)->orWhere('sku', $search);
                if (ctype_digit($search)) {
                    $query->orWhere('id', (int) $search);
                }
            })->orderBy('name')->limit(30)->get();

        return response()->json(['options' => $products->map(fn (Product $product): array => [
            'value' => (string) $product->id,
            'label' => $product->name.' · ID '.$product->id.' · '.($product->status ? 'Активен' : 'Неактивен')
                .' · '.implode(' · ', array_filter([$product->code, $product->sku, $product->attributeValues->pluck('value')->implode(', ')])),
        ])->all()]);
    }

    private function adminId(): int
    {
        return (int) request()->user(config('moonshine.auth.guard'))->getAuthIdentifier();
    }

    private function pageNumber(int $total): int
    {
        return min(max(1, request()->integer('unlinked_page', 1)), max(1, (int) ceil($total / 200)));
    }

    private function pagination(string $id, int $total): array
    {
        $page = $this->pageNumber($total);
        $pages = max(1, (int) ceil($total / 200));
        $result = [Heading::make('Страница '.$page.' из '.$pages, h: 6)];
        if ($page > 1) {
            $result[] = Link::make($this->getRoute(['import' => $id, 'unlinked_page' => $page - 1]), '← Предыдущая страница');
        }
        if ($page < $pages) {
            $result[] = Link::make($this->getRoute(['import' => $id, 'unlinked_page' => $page + 1]), 'Следующая страница →');
        }

        return $result;
    }

    private function rowModal(array $row, string $context): string
    {
        $details = TableBuilder::make([Text::make('Код 1С', 'code'), Text::make('Название', 'name'), Text::make('Цена', 'price'), Text::make('Остаток', 'quantity')])->items([$row])->vertical(title: 2, value: 10)->simple()->preview()->class('table-divider');

        return (string) ActionButton::make(e($row['name']))->inModal(title: 'Позиция 1С', name: 'onec-row-'.$context.'-'.md5($row['code']), builder: fn (ModalContract $modal): ModalContract => $modal->wide(), components: [$details])->render();
    }

    private function productModal(int $id, string $name, string $context): string
    {
        return (string) ActionButton::make(e($name), app(ProductResource::class)->getDetailPageUrl($id, fragment: 'crud-detail'))->async()->inModal(title: 'Карточка товара', name: 'onec-product-'.$context.'-'.$id, builder: fn (ModalContract $modal): ModalContract => $modal->wide())->render();
    }

    private function selectionButton(string $id, array $row): string
    {
        $selected = $row['selected'];
        $button = ActionButton::make($selected ? 'Изменить выбор' : 'Выбрать товар')->inOffCanvas(title: 'Подобрать товар для проверки', name: 'onec-select-'.md5($row['code']), content: fn (): string => (string) FormBuilder::make()->asyncMethod('selectProduct', page: $this)->fields([
            Heading::make(e($row['name']).' — '.e($row['price']), h: 6), Hidden::make('import')->setValue($id), Hidden::make('code')->setValue($row['code']), Hidden::make('unlinked_page')->setValue(request()->integer('unlinked_page', 1)),
            Select::make('Найти товар по названию, ID, коду или артикулу', 'product_id')->nullable()->required()->async($this->getCore()->getRouter()->getEndpoints()->method('searchProducts', page: $this))->settings(['wrapperClass' => 'ts-wrapper supplier-product-select', 'dropdownClass' => 'ts-dropdown supplier-product-options'])->hint('Сравните модель и характеристики. Товар с кодом 1С выбрать нельзя. Неактивный товар останется выключенным.'),
        ])->submit('Добавить на проверку')->render());
        if ($selected) {
            $button->primary();
        }

        return (string) $button->render();
    }

    #[AsyncMethod]
    public function selectProduct(Request $request, OneCModeration $service): MoonShineJsonResponse
    {
        if (! $request->isMethod('POST')) {
            return MoonShineJsonResponse::make()->setStatusCode(405);
        }
        $data = $request->validate(['import' => ['required', 'string'], 'code' => ['required', 'string'], 'product_id' => ['required', 'integer'], 'unlinked_page' => ['nullable', 'integer', 'min:1']]);
        $service->select($data['import'], $this->adminId(), $data['code'], (int) $data['product_id']);

        return MoonShineJsonResponse::make()->toast('Добавлено на проверку. Привязка ещё не применена.', ToastType::SUCCESS)->redirect($this->getRoute(['import' => $data['import'], 'unlinked_page' => $data['unlinked_page'] ?? 1]));
    }

    #[AsyncMethod]
    public function removeDraft(Request $request, OneCModeration $service): MoonShineJsonResponse
    {
        if (! $request->isMethod('POST')) {
            return MoonShineJsonResponse::make()->setStatusCode(405);
        }
        $data = $request->validate(['import' => ['required', 'string'], 'code' => ['required', 'string']]);
        $service->drafts($data['import'], $this->adminId())->where('code', $data['code'])->delete();

        return MoonShineJsonResponse::make()->redirect($this->getRoute(['import' => $data['import']]));
    }

    #[AsyncMethod]
    public function applyDrafts(Request $request, OneCModeration $service): MoonShineJsonResponse
    {
        if (! $request->isMethod('POST')) {
            return MoonShineJsonResponse::make()->setStatusCode(405);
        }
        $data = $request->validate(['import' => ['required', 'string']]);
        try {
            $count = $service->applyDrafts($data['import'], $this->adminId());
        } catch (RuntimeException $exception) {
            return MoonShineJsonResponse::make()->toast($exception->getMessage(), ToastType::ERROR);
        }

        return MoonShineJsonResponse::make()->toast('Привязано позиций: '.$count, ToastType::SUCCESS)->redirect($this->getRoute(['import' => $data['import']]));
    }

    private function draftBox(string $id): array
    {
        $service = app(OneCModeration::class);
        $drafts = $service->drafts($id, $this->adminId())->get();
        if ($drafts->isEmpty()) {
            return [];
        }
        $rows = $service->rows($id);
        $products = Product::query()->whereIn('id', $drafts->pluck('product_id'))->get()->keyBy('id');
        $items = $drafts->map(fn ($draft): array => ['code' => $draft->code, 'name' => $rows[$draft->code]['name'] ?? 'Позиция отсутствует', 'price' => isset($rows[$draft->code]['price']) ? $this->money($rows[$draft->code]['price']) : 'Не указана', 'quantity' => $rows[$draft->code]['quantity'] ?? 'Не указан', 'product' => $products->get($draft->product_id)]);

        return [Box::make('На проверке ('.$drafts->count().')', [
            Heading::make('Проверьте каждую пару. Черновик сохранён для вашей учётной записи. После подтверждения запишутся код 1С, цена и остаток из файла.', h: 6),
            TableBuilder::make(items: $items->all())->customAttributes(['data-wrap-text' => ''])->fields([
                Text::make('Позиция 1С', 'name', fn (array $row): string => $this->rowModal($row, 'draft'))->unescape(),
                Text::make('Выбранный товар', 'product', fn (array $row): string => $row['product'] ? $this->productModal($row['product']->id, $row['product']->name.' · ID '.$row['product']->id.' · '.($row['product']->status ? 'Активен' : 'Неактивен'), 'draft') : 'Товар удалён')->unescape(),
                Text::make('Действие', 'remove', fn (array $row): string => (string) ActionButton::make('Убрать')->method('removeDraft', params: ['import' => $id, 'code' => $row['code']])->removeAttribute('data-async-method')->async(HttpMethod::POST)->render())->unescape(),
            ]), ActionButton::make('Применить все привязки')->method('applyDrafts', params: ['import' => $id])->withConfirm(title: 'Применить привязки?', content: 'Будут записаны код 1С, цена и остаток выбранных товаров. Если пара недоступна, весь пакет будет отменён.', button: 'Применить все')->primary(),
        ])];
    }

    private function recentUploads(OneCImportFiles $files): Box
    {
        return Box::make('Последние загрузки', [
            TableBuilder::make(items: $files->recent()->map(fn (array $upload): array => [
                'id' => $upload['id'],
                'name' => $upload['original_name'],
                'uploaded' => $upload['uploaded_at']?->format('d.m.Y H:i') ?? '',
                'applied_by' => $upload['applied_at'] ? ($upload['applied_by_name'] ?? 'Неизвестно') : '—',
                'status' => $upload['applied_at'] ? 'применено '.$upload['applied_at']->format('d.m.Y H:i').' ('.$upload['applied_count'].')' : 'не применено',
            ])->all())
                ->fields([
                    Text::make('Дата', 'uploaded'),
                    Text::make('Файл', 'name', fn (array $row): string => (string) Link::make($this->getRoute(['import' => $row['id']]), $row['name'])->render())->unescape(),
                    Text::make('Состояние', 'status'),
                    Text::make('Кто применил', 'applied_by'),
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
