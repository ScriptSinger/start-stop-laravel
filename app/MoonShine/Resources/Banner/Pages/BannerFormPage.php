<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Banner\Pages;

use App\Enums\BannerPosition;
use App\MoonShine\Resources\Banner\BannerResource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\FormBuilderContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends FormPage<BannerResource>
 */
class BannerFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                Enum::make('Где показывать', 'position')->attach(BannerPosition::class)->required(),
                Image::make('Картинка', 'image')
                    // Папка — в имени, а не в dir(): dir() дописывается и к уже
                    // сохранённым путям, и картинки со старого сайта
                    // (catalog/revslider_media_folder/…) не показывались бы.
                    ->customName(fn (UploadedFile $file): string => 'banners/'.Str::random(40).'.'.$file->extension())
                    ->allowedExtensions(['jpg', 'jpeg', 'png', 'webp'])
                    ->hint('Слайдер — 960×350 px, полоса под слайдером — 960×132 px. Другой размер растянется или обрежется по краям.'),
                Text::make('Подпись', 'title')->nullable()->hint('Не видна на сайте: для поисковиков и незрячих. Например: «Трейд-ин: сдайте старый аккумулятор»'),
                Text::make('Ссылка', 'url')->nullable()->hint('Куда ведёт клик: /page/trade-in, /category/akkumulyatori или полный адрес. Пусто — баннер без ссылки.'),
                Number::make('Порядок', 'sort_order')->default(0)->hint('Меньше — раньше в слайдере'),
                Switcher::make('Показывать', 'is_active')->default(true),
            ]),
        ];
    }

    protected function buttons(): ListOf
    {
        return parent::buttons();
    }

    protected function formButtons(): ListOf
    {
        return parent::formButtons();
    }

    /**
     * Картинка обязательна у нового баннера; при правке старая остаётся,
     * если не загружать новую.
     */
    protected function rules(DataWrapperContract $item): array
    {
        return [
            'position' => ['required'],
            'image' => [$item->getKey() ? 'nullable' : 'required', 'image', 'max:2048'],
            'url' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @param  FormBuilder  $component
     * @return FormBuilder
     */
    protected function modifyFormComponent(FormBuilderContract $component): FormBuilderContract
    {
        return $component;
    }

    /**
     * @return list<ComponentContract>
     *
     * @throws Throwable
     */
    protected function topLayer(): array
    {
        return [
            ...parent::topLayer(),
        ];
    }

    /**
     * @return list<ComponentContract>
     *
     * @throws Throwable
     */
    protected function mainLayer(): array
    {
        return [
            ...parent::mainLayer(),
        ];
    }

    /**
     * @return list<ComponentContract>
     *
     * @throws Throwable
     */
    protected function bottomLayer(): array
    {
        return [
            ...parent::bottomLayer(),
        ];
    }
}
