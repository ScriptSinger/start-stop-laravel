<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Enums\SupplierPriceFormat;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use App\MoonShine\Resources\Product\ProductResource;
use App\MoonShine\Resources\Supplier\SupplierResource;
use App\Services\Supplier\SupplierImportApplication;
use App\Services\Supplier\SupplierImportFiles;
use App\Services\Supplier\SupplierImportReport;
use App\Services\Supplier\SupplierLinkDrafts;
use App\Services\Supplier\SupplierOfferLinker;
use App\Services\Supplier\SupplierPriceImporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Components\Collapse;
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
    public function apply(Request $request, SupplierImportApplication $application): MoonShineJsonResponse
    {
        if (! $request->isMethod('POST')) {
            return MoonShineJsonResponse::make()->setStatusCode(405);
        }
        $data = $request->validate(['import' => ['required', 'string']]);
        $id = $data['import'];
        $admin = $request->user(config('moonshine.auth.guard'));
        try {
            $report = $application->apply($id, (int) $admin->getAuthIdentifier(), $admin->name);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return MoonShineJsonResponse::make()->toast($exception->getMessage(), ToastType::ERROR);
        }

        return MoonShineJsonResponse::make()
            ->toast("Применено: стало под заказ — {$report->productsAdded}, изменилась цена — {$report->productsRepriced}, снято — {$report->productsCleared}. Копия базы снята перед записью.", ToastType::SUCCESS)
            ->redirect($this->getRoute(['import' => $id]));
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

    #[AsyncMethod]
    public function linkOffer(Request $request, SupplierOfferLinker $linker): MoonShineJsonResponse
    {
        if (! $request->isMethod('POST')) {
            return MoonShineJsonResponse::make()->setStatusCode(405);
        }
        $data = $request->validate([
            'import' => ['required', 'string'],
            'offer_id' => ['required', 'integer'],
            'product_id' => ['required', 'integer'],
            'unlinked_page' => ['nullable', 'integer', 'min:1'],
        ]);
        $linker->select($data['import'], $this->adminId(), (int) $data['offer_id'], (int) $data['product_id']);

        return MoonShineJsonResponse::make()
            ->toast('Добавлено на проверку. Привязка ещё не сохранена.', ToastType::SUCCESS)
            ->redirect($this->getRoute(['import' => $data['import'], ...(($data['unlinked_page'] ?? 1) > 1 ? ['unlinked_page' => $data['unlinked_page']] : [])]));
    }

    #[AsyncMethod]
    public function removeDraftLink(Request $request, SupplierImportFiles $files, SupplierLinkDrafts $drafts): MoonShineJsonResponse
    {
        if (! $request->isMethod('POST')) {
            return MoonShineJsonResponse::make()->setStatusCode(405);
        }
        $data = $request->validate(['import' => ['required', 'string'], 'offer_id' => ['required', 'integer'], 'unlinked_page' => ['nullable', 'integer', 'min:1']]);
        if ($files->path($data['import']) === null) {
            throw ValidationException::withMessages(['import' => 'Загрузка не найдена.']);
        }
        $this->draftSelections($data['import']);
        $drafts->remove($data['import'], $this->adminId(), (int) $data['offer_id']);

        return MoonShineJsonResponse::make()->toast('Удалено из списка проверки.', ToastType::SUCCESS)
            ->redirect($this->getRoute(['import' => $data['import'], ...(($data['unlinked_page'] ?? 1) > 1 ? ['unlinked_page' => $data['unlinked_page']] : [])]));
    }

    #[AsyncMethod]
    public function applyDraftLinks(Request $request, SupplierOfferLinker $linker): MoonShineJsonResponse
    {
        if (! $request->isMethod('POST')) {
            return MoonShineJsonResponse::make()->setStatusCode(405);
        }
        $data = $request->validate(['import' => ['required', 'string']]);
        $this->draftSelections($data['import']);
        try {
            $count = $linker->apply($data['import'], $this->adminId());
        } catch (RuntimeException $exception) {
            return MoonShineJsonResponse::make()->toast($exception->getMessage(), ToastType::ERROR);
        }

        return MoonShineJsonResponse::make()
            ->toast('Привязано предложений: '.$count.'. Доступность под заказ пересчитана.', ToastType::SUCCESS)
            ->redirect($this->getRoute(['import' => $data['import']]));
    }

    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        $files = app(SupplierImportFiles::class);
        $id = (string) request()->query('import', '');
        $path = $id !== '' ? $files->path($id) : null;

        if ($id !== '') {
            return [
                Link::make($this->getRoute(), '← К загрузкам прайсов')->class('mb-4'),
                ...($path !== null
                    ? $this->report($id, $files, $path)
                    : [Box::make('Отчёт', [Heading::make('Загрузка не найдена. Вернитесь к списку и загрузите файл заново.', h: 6)])]),
            ];
        }

        return [$this->uploadForm(), $this->recentUploads($files)];
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

        $applied = isset($meta['applied_at']);
        $summary = [
            Heading::make($this->status($meta, $report), h: 6),
            Heading::make('Цена под заказ = закупка + наценка. Отсутствующие в файле позиции исключаются из прайса.', h: 6),
            Collapse::make('Как работает загрузка', [
                Heading::make('Цена под заказ округляется вверх до 50 ₽. Предложение доступно при достаточном остатке поставщика или если количество не учитывается. При собственном остатке действуют цена и остаток 1С.', h: 6),
            ])->persist(false),
        ];

        if ($report->rows === 0) {
            $summary[] = Alert::make(type: 'error')->content(
                'Не удалось распознать ни одной позиции. Проверьте файл и выбранного поставщика. Применение заблокировано: в этом отчёте было бы исключено позиций: '.$report->vanished.'.'
            );
        } elseif (! $applied) {
            $summary[] = ActionButton::make('Применить к базе')
                ->method('apply', params: ['import' => $id])
                ->withConfirm(
                    title: 'Применить прайс?',
                    content: 'Будет создана копия базы, затем обновятся предложения поставщика и товары под заказ.',
                    button: 'Применить',
                )
                ->primary()
                ->icon('check');
        }

        return [
            Box::make('Отчёт по файлу «'.($meta['original_name'] ?? $id).'» — '.$supplier->name, $summary),
            Grid::make([
                $this->metric('Строк распознано', $report->rows),
                $this->metric('Новых предложений', $report->created),
                $this->metric('Изменится закупка', $report->costChanged),
                $this->metric('Будет снято из прайса', $report->vanished),
                $this->metric('Добавится поставщик', $report->productsAdded),
                $this->metric('Изменится цена или поставщик', $report->productsRepriced),
                $this->metric('Потеряет поставщика', $report->productsCleared),
                $this->metric('Без привязки к товару', $report->unlinked->count()),
            ]),
            Box::make('Изменения закупки', $report->costChanges->isEmpty()
                ? [Heading::make('Изменений закупки нет.', h: 6)]
                : [Heading::make('Изменилась закупка ('.$report->costChanges->count().')', h: 6), $this->costChangesTable($report)]),
            ...$this->draftBox($id, $supplier),
            Box::make('Без товара на сайте ('.$report->unlinked->count().')', $report->unlinked->isEmpty()
                ? [Heading::make('Предложений без привязки к товару нет.', h: 6)]
                : [
                    Heading::make('Предложения без привязки к карточке сайта. После применения прайса добавьте соответствия на проверку, затем примените их вместе.', h: 6),
                    $this->unlinkedTable($report, $supplier, $id, $applied),
                    ...$this->unlinkedPagination($id, $report->unlinked->count()),
                ]),
        ];
    }

    /**
     * @param  array{supplier_id?: int, extension?: string, original_name?: string, uploaded_at?: string, applied_at?: string, summary?: array<string, int>}  $meta
     */
    private function status(array $meta, SupplierImportReport $report): string
    {
        if (isset($meta['applied_at'])) {
            return 'Прайс применён '.date('d.m.Y H:i', (int) strtotime($meta['applied_at'])).'. Показатели ниже рассчитаны относительно текущей базы.';
        }

        return 'Не применено: данные на сайте пока не изменены. После применения: станет под заказ — '.$report->productsAdded.', изменится цена — '.$report->productsRepriced.', снимется — '.$report->productsCleared.'.';
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
            ->customAttributes(['data-resizable' => '', 'data-widths' => '75,25'])
            ->withNotFound();
    }

    private function adminId(): int
    {
        return (int) request()->user(config('moonshine.auth.guard'))->getAuthIdentifier();
    }

    /** @return array<int, int> */
    private function draftSelections(string $importId): array
    {
        $drafts = app(SupplierLinkDrafts::class);
        $legacy = request()->session()->get('supplier_link_drafts.'.$importId, []);
        $current = $drafts->selections($importId, $this->adminId());
        foreach ($legacy as $offerId => $productId) {
            if (isset($current[$offerId])) {
                continue;
            }
            try {
                app(SupplierOfferLinker::class)->select($importId, $this->adminId(), (int) $offerId, (int) $productId);
            } catch (ValidationException) {
                continue;
            }
        }
        request()->session()->forget('supplier_link_drafts.'.$importId);

        return $legacy === [] ? $current : $drafts->selections($importId, $this->adminId());
    }

    private function unlinkedPage(int $total): int
    {
        return min(max(1, request()->integer('unlinked_page', 1)), max(1, (int) ceil($total / self::MAX_ROWS_SHOWN)));
    }

    /** @return list<ComponentContract> */
    private function unlinkedPagination(string $importId, int $total): array
    {
        $page = $this->unlinkedPage($total);
        $pages = max(1, (int) ceil($total / self::MAX_ROWS_SHOWN));
        $components = [Heading::make('Страница '.$page.' из '.$pages.'. Всего позиций: '.$total.'. Выбор сохраняется при переходе между страницами.', h: 6)];
        if ($page > 1) {
            $components[] = Link::make($this->getRoute(['import' => $importId, 'unlinked_page' => $page - 1]), '← Предыдущая страница');
        }
        if ($page < $pages) {
            $components[] = Link::make($this->getRoute(['import' => $importId, 'unlinked_page' => $page + 1]), 'Следующая страница →');
        }

        return $components;
    }

    private function unlinkedTable(SupplierImportReport $report, Supplier $supplier, string $importId, bool $applied): TableBuilder
    {
        // Только у АКБ АВТО в прайсе есть настоящий код; у остальных «код» — это само название.
        $hasCodes = $supplier->format === SupplierPriceFormat::AkbAuto;

        $offers = $applied ? $supplier->offers()->whereNull('product_id')->where('in_price', true)->get()->keyBy('supplier_code') : collect();
        $draft = $this->draftSelections($importId);

        return TableBuilder::make(items: $report->unlinked->forPage($this->unlinkedPage($report->unlinked->count()), self::MAX_ROWS_SHOWN)->map(fn (array $row): array => [
            'offer_id' => $offers->get($row['code'])?->id,
            'offer_record' => $offers->get($row['code']),
            'draft_product_id' => $draft[$offers->get($row['code'])?->id] ?? null,
            'code' => $row['code'],
            'name' => $row['name'],
            'cost' => $this->money($row['cost']),
        ])->all())
            ->fields([
                ...($hasCodes ? [Text::make('Код', 'code')] : []),
                Text::make('Название в прайсе', 'name', fn (array $row): string => $row['offer_record']
                    ? $this->offerDetailsButton($row['offer_record'], $supplier, 'unlinked')
                    : e($row['name']))->unescape(),
                Text::make('Закупка', 'cost'),
                Text::make('Товар на сайте', 'link', function (array $row) use ($importId): string {
                    if (! $row['offer_id']) {
                        return 'Сначала примените актуальный прайс';
                    }
                    if ($row['draft_product_id'] !== null) {
                        return (string) $this->linkButton($row, $importId, 'Изменить выбор')
                            ->primary()->render();
                    }

                    return (string) $this->linkButton($row, $importId)->render();
                })->unescape(),
            ])
            ->customAttributes(['data-resizable' => '', 'data-wrap-text' => '', 'data-widths' => $hasCodes ? '15,45,15,25' : '55,20,25'])
            ->withNotFound();
    }

    /**
     * @param  array{offer_id: int, code: string, name: string, cost: string}  $row
     */
    private function linkButton(array $row, string $importId, string $label = 'Выбрать товар'): ActionButton
    {
        return ActionButton::make($label)
            ->inOffCanvas(
                title: 'Подобрать товар для проверки',
                content: fn (): string => (string) FormBuilder::make()
                    ->asyncMethod('linkOffer', page: $this)
                    ->fields([
                        Heading::make(e($row['name']).' — '.e($row['cost']), h: 6),
                        Hidden::make('import')->setValue($importId),
                        Hidden::make('unlinked_page')->setValue(max(1, request()->integer('unlinked_page', 1))),
                        Hidden::make('offer_id')->setValue($row['offer_id']),
                        Select::make('Найти товар по названию, ID, коду или артикулу', 'product_id')
                            ->customAttributes(['id' => 'link-product-'.$row['offer_id']])
                            ->nullable()->required()
                            ->async($this->getCore()->getRouter()->getEndpoints()->method('searchProducts', page: $this))
                            ->settings(['wrapperClass' => 'ts-wrapper supplier-product-select', 'dropdownClass' => 'ts-dropdown supplier-product-options'])
                            ->hint('Сравните модель, ёмкость, полярность и габариты. Неактивный товар останется выключенным.'),
                    ])->submit('Добавить на проверку')->render(),
                name: 'link-offer-'.$row['offer_id'],
            );
    }

    /**
     * @return list<ComponentContract>
     */
    private function draftBox(string $importId, Supplier $supplier): array
    {
        $draft = $this->draftSelections($importId);
        if ($draft === []) {
            return [];
        }
        $offers = $supplier->offers()->whereIn('id', array_keys($draft))->get()->keyBy('id');
        $products = Product::query()->whereIn('id', array_values($draft))->with('attributeValues')->get()->keyBy('id');
        $rows = collect($draft)->map(function (int $productId, int|string $offerId) use ($offers, $products): array {
            $offer = $offers->get($offerId);
            $product = $products->get($productId);

            return [
                'offer_id' => $offerId,
                'offer_record' => $offer,
                'product_id' => $product?->id,
                'offer' => $offer?->name ?? 'Предложение удалено',
                'product' => $product ? $product->name.' · ID '.$product->id.' · '.($product->status ? 'Активен' : 'Неактивен').' · '.$product->attributeValues->pluck('value')->implode(', ') : 'Товар удалён',
            ];
        })->values();

        return [Box::make('На проверке ('.$rows->count().')', [
            Heading::make('Проверьте каждую пару. Черновик сохранён для вашей учётной записи; привязки ещё не применены. Ненужные пары можно удалить или выбрать товар заново.', h: 6),
            TableBuilder::make(items: $rows->all())->customAttributes(['data-wrap-text' => ''])->fields([
                Text::make('Позиция поставщика', 'offer', function (array $row) use ($supplier): string {
                    $offer = $row['offer_record'];
                    if ($offer === null) {
                        return e($row['offer']);
                    }

                    return $this->offerDetailsButton($offer, $supplier, 'draft');
                })->unescape(),
                Text::make('Выбранный товар', 'product', fn (array $row): string => $row['product_id'] === null
                    ? e($row['product'])
                    : (string) ActionButton::make(e($row['product']), app(ProductResource::class)->getDetailPageUrl($row['product_id'], fragment: 'crud-detail'))
                        ->async()->inModal(
                            title: 'Карточка товара',
                            name: 'supplier-product-detail-'.$row['offer_id'],
                            builder: fn (ModalContract $modal): ModalContract => $modal->wide(),
                        )
                        ->customAttributes(['title' => 'Открыть карточку товара'])
                        ->render())->unescape(),
                Text::make('Действие', 'remove', fn (array $row): string => (string) ActionButton::make('Убрать')
                    ->method('removeDraftLink', params: ['import' => $importId, 'offer_id' => $row['offer_id'], 'unlinked_page' => max(1, request()->integer('unlinked_page', 1))])
                    ->removeAttribute('data-async-method')->async(HttpMethod::POST)->render())->unescape(),
            ]),
            ActionButton::make('Применить все привязки')
                ->method('applyDraftLinks', params: ['import' => $importId])
                ->withConfirm(title: 'Применить привязки?', content: 'Все пары будут проверены повторно. Если одна из них изменилась, ничего не сохранится.', button: 'Применить все')
                ->primary(),
        ])];
    }

    private function offerDetailsButton(SupplierOffer $offer, Supplier $supplier, string $context): string
    {
        $details = TableBuilder::make([
            Text::make('Поставщик', 'supplier'),
            Text::make('Название', 'name'),
            Text::make('Код поставщика', 'code'),
            Text::make('Закупочная цена', 'cost'),
            Text::make('Остаток у поставщика', 'quantity'),
            Text::make('В актуальном прайсе', 'in_price'),
            Text::make('Товар на сайте', 'product'),
        ])->items([[
            'supplier' => $supplier->name,
            'name' => $offer->name,
            'code' => $supplier->format === SupplierPriceFormat::AkbAuto
                ? $offer->supplier_code
                : 'В этом формате используется название позиции',
            'cost' => $this->money((float) $offer->cost),
            'quantity' => $offer->quantity === null ? 'Не указан' : $offer->quantity.' шт.',
            'in_price' => $offer->in_price ? 'Да' : 'Нет',
            'product' => $offer->product_id === null ? 'Не привязан' : 'ID '.$offer->product_id,
        ]])->vertical(title: 2, value: 10)->simple()->preview()->class('table-divider');

        return (string) ActionButton::make(e($offer->name))
            ->inModal(title: 'Позиция поставщика', name: 'supplier-offer-detail-'.($context === 'draft' ? '' : $context.'-').$offer->id, builder: fn (ModalContract $modal): ModalContract => $modal->wide(), components: [$details])
            ->customAttributes(['title' => 'Открыть позицию поставщика'])
            ->render();
    }

    private function recentUploads(SupplierImportFiles $files): Box
    {
        $suppliers = Supplier::query()->pluck('name', 'id');
        $supplierPage = app(SupplierResource::class)->getIndexPageUrl();

        $rows = $files->recent()->map(fn (array $upload): array => [
            'id' => $upload['id'],
            'name' => $upload['original_name'],
            'supplier' => (string) $suppliers->get($upload['supplier_id'], ''),
            'uploaded' => $upload['uploaded_at']?->format('d.m.Y H:i') ?? '',
            'uploaded_sort' => $upload['uploaded_at']?->timestamp ?? 0,
            'applied_by' => $upload['applied_at'] ? ($upload['applied_by_name'] ?? 'Неизвестно') : '—',
            'status' => $upload['applied_at'] ? 'применено '.$upload['applied_at']->format('d.m.Y H:i') : 'не применено',
        ]);
        $sort = request()->query('sort');
        if (is_string($sort)) {
            $column = ltrim($sort, '-');
            $sortColumns = ['uploaded' => 'uploaded_sort', 'supplier' => 'supplier', 'name' => 'name', 'status' => 'status', 'applied_by' => 'applied_by'];
            if (isset($sortColumns[$column]) && ($sort === $column || $sort === '-'.$column)) {
                $rows = $rows->sortBy($sortColumns[$column], SORT_NATURAL | SORT_FLAG_CASE, str_starts_with($sort, '-'))->values();
            }
        }

        return Box::make('Последние загрузки', [
            Link::make($supplierPage, 'Поставщики и их настройки'),
            TableBuilder::make(items: $rows->all())
                ->fields([
                    Text::make('Дата', 'uploaded')->sortable(),
                    Text::make('Поставщик', 'supplier')->sortable(),
                    Text::make('Файл', 'name', fn (array $row): string => (string) Link::make($this->getRoute(['import' => $row['id']]), $row['name'])->render())->unescape()->sortable(),
                    Text::make('Состояние', 'status')->sortable(),
                    Text::make('Кто применил', 'applied_by')->sortable(),
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
