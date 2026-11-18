<?php

declare(strict_types=1);

namespace App\MoonShine\Handlers;

use App\Services\Catalog\AvitoExport;
use Illuminate\Support\Facades\Storage;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\ImportExport\ExportHandler;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Exceptions\ActionButtonException;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Выгрузка для Авито: всегда весь ассортимент в наличии и под заказ — фильтры
 * списка не учитываются, колонки заданы в AvitoExport.
 */
class AvitoExportHandler extends ExportHandler
{
    protected function getDefaultFilename(): string
    {
        return 'avito-'.now()->format('Y-m-d');
    }

    public function handle(): Response
    {
        if (! $this->hasResource()) {
            throw ActionButtonException::resourceRequired();
        }

        $this->resolveStorage();

        $path = Storage::disk($this->getDisk())->path($this->generateFilePath());

        (new FastExcel(app(AvitoExport::class)->rows()))->export($path);

        return response()->download($path)->deleteFileAfterSend();
    }

    /**
     * Кнопка без текущих фильтров и сортировки списка.
     */
    public function getButton(): ActionButtonContract
    {
        return $this->prepareButton(
            ActionButton::make($this->getLabel(), $this->getUrl())
                ->primary()
                ->icon($this->getIconValue(), $this->isCustomIcon(), $this->getIconPath()),
        );
    }
}
