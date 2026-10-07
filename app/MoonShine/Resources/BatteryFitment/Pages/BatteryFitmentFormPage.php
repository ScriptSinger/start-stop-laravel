<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\BatteryFitment\Pages;

use App\Models\BatteryFitment;
use App\MoonShine\Resources\BatteryFitment\BatteryFitmentResource;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\FormBuilderContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends FormPage<BatteryFitmentResource>
 */
class BatteryFitmentFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                Text::make('Марка', 'brand'),
                Text::make('Модель', 'model'),
                Text::make('Поколение', 'generation')->nullable(),
                Text::make('Двигатель', 'engine')->nullable(),
                // Через запятую, как в выгрузке подбора (podbor.xlsx).
                Text::make('Ёмкость (Ач)', 'capacity')->nullable()->hint('Через запятую: 60 Ач, 62 Ач'),
                Text::make('Полярность', 'polarity')->nullable()->hint('Одна или несколько через запятую: Обратная, Универсальная'),
                Text::make('Габариты (ДxШxВ, через запятую)', 'dims')->nullable(),
                Select::make('Клеммы', 'terminals')
                    ->options(collect(config('shop.battery_fitment.terminal_values'))
                        ->keys()
                        ->mapWithKeys(fn (string $key): array => [$key => (string) (new BatteryFitment(['terminals' => $key]))->terminalsLabel()])
                        ->all())
                    ->nullable(),
                Image::make('Фото авто', 'image')->nullable(),
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

    protected function rules(DataWrapperContract $item): array
    {
        return [];
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
