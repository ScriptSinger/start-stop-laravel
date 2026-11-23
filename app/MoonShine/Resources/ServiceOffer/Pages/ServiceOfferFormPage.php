<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ServiceOffer\Pages;

use App\Enums\ServicePriceType;
use App\Models\ServiceOffer;
use App\MoonShine\Resources\ServiceOffer\ServiceOfferResource;
use Closure;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends FormPage<ServiceOfferResource>
 */
class ServiceOfferFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        $exists = $this->getResource()->getItem()?->exists === true;

        return [
            Box::make([
                ID::make(),
                Text::make('Якорь (slug)', 'slug')->required()->readonly($exists)
                    ->hint('Латиница и дефис: на карточку можно сослаться как /page/uslugi#slug. После создания не меняется'),
                Text::make('Название', 'title')->required(),
                Text::make('Иконка', 'icon')->required()
                    ->hint('Класс Font Awesome, например fa-bolt'),
                Textarea::make('Описание', 'description')->required(),
                Enum::make('Тип цены', 'price_type')->attach(ServicePriceType::class)->required(),
                Number::make('Сумма, ₽', 'price')->min(0)->nullable()
                    ->showWhen('price_type', 'in', ServicePriceType::valuesWithAmount())
                    ->hint('Для типов «Фиксированная цена» и «Цена от»'),
                Textarea::make('Условия', 'terms')->nullable()
                    ->hint('Необязательно: продолжение подписи цены, например «при покупке аккумулятора в нашем магазине»'),
                Number::make('Порядок', 'sort_order')->default(0),
                Switcher::make('Показывать на сайте', 'is_active')->default(true),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(DataWrapperContract $item): array
    {
        $offer = $item->getOriginal();
        $original = $offer instanceof ServiceOffer && $offer->exists ? $offer->slug : null;

        return [
            'slug' => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('service_offers', 'slug')->ignore($item->getKey()),
                $this->slugIsImmutable($original),
            ],
            'title' => ['required', 'string', 'max:255'],
            'icon' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string'],
            'price_type' => ['required', Rule::enum(ServicePriceType::class)],
            'price' => [
                Rule::requiredIf(fn (): bool => in_array(request()->input('price_type'), ServicePriceType::valuesWithAmount(), true)),
                'nullable', 'integer', 'min:0',
            ],
            'terms' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Якорь #slug нельзя менять после создания: на него могут ссылаться.
     *
     * @return Closure(string, mixed, Closure(string): void): void
     */
    private function slugIsImmutable(?string $original): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($original): void {
            if ($original !== null && $value !== $original) {
                $fail('Якорь после создания не меняется.');
            }
        };
    }
}
