<?php

declare(strict_types=1);

namespace App\MoonShine\Handlers;

use App\Services\Backup\DatabaseBackup;
use Illuminate\Support\Facades\DB;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Contracts\UI\FormBuilderContract;
use MoonShine\ImportExport\ImportHandler;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Exceptions\ActionButtonException;
use MoonShine\UI\Fields\File;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Импорт из Excel (замена Batch Editor старой админки): правит сразу много
 * записей, поэтому перед ним — копия базы, а сам он — одной транзакцией:
 * ошибка в любой строке, и не меняется ничего.
 */
class SafeImportHandler extends ImportHandler
{
    private string $hint = '';

    /**
     * Подсказка над полем файла: откуда взять файл и что он меняет.
     */
    public function hint(string $hint): static
    {
        $this->hint = $hint;

        return $this;
    }

    public function handle(): Response
    {
        if (! request()->hasFile($this->getInputName())) {
            return parent::handle();
        }

        try {
            $this->backup();
        } catch (RuntimeException $exception) {
            toast('Импорт отменён: не удалось снять копию базы. '.$exception->getMessage(), ToastType::ERROR);

            return back();
        }

        try {
            return DB::transaction(fn (): Response => parent::handle());
        } catch (Throwable $exception) {
            report($exception);
            toast('Импорт не выполнен, ничего не изменено: '.$exception->getMessage(), ToastType::ERROR);

            return back();
        }
    }

    /**
     * @throws ActionButtonException
     */
    public function getButton(): ActionButtonContract
    {
        if (! $this->hasResource()) {
            throw ActionButtonException::resourceRequired();
        }

        return $this->prepareButton(
            ActionButton::make($this->getLabel())
                ->success()
                ->icon($this->getIconValue(), $this->isCustomIcon(), $this->getIconPath())
                ->inOffCanvas(
                    fn (): string => $this->getLabel(),
                    fn (): FormBuilderContract => FormBuilder::make($this->getUrl())
                        ->fields([
                            Heading::make($this->hint.' Перед импортом снимается копия базы; если в файле ошибка, не изменится ничего.', h: 6),
                            File::make(column: $this->getInputName())->required(),
                        ])
                        ->class('js-change-query')
                        ->customAttributes(['data-original-url' => $this->getUrl()])
                        ->submit('Загрузить'),
                    name: 'import-off-canvas'
                )
        );
    }

    /**
     * Копия — только на MariaDB/MySQL (на сервере и локально); в тестах база
     * в памяти, копировать нечего.
     */
    private function backup(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            app(DatabaseBackup::class)->create('before-import');
        }
    }
}
