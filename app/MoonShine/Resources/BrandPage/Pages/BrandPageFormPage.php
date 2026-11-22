<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\BrandPage\Pages;

use App\MoonShine\Fields\SeoFields;
use App\MoonShine\Resources\BrandPage\BrandPageResource;
use App\MoonShine\Resources\Category\CategoryResource;
use App\MoonShine\Resources\Manufacturer\ManufacturerResource;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\TinyMce\Fields\TinyMce;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;

/**
 * @extends FormPage<BrandPageResource>
 */
class BrandPageFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                BelongsTo::make('Раздел', 'category', resource: CategoryResource::class)
                    ->hint('Страница есть только у разделов из config/shop.php → brand_pages.category_slugs'),
                BelongsTo::make('Производитель', 'manufacturer', resource: ManufacturerResource::class),
                TinyMce::make('Описание', 'description')->locale('ru')->nullable()
                    ->hint('Текст страницы производителя вместо описания раздела. Пусто — текста нет'),
            ]),
            Box::make('SEO', SeoFields::make('«раздел + производитель»')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(DataWrapperContract $item): array
    {
        return [
            'category_id' => [
                'required',
                Rule::unique('brand_pages', 'category_id')
                    ->where('manufacturer_id', request()->input('manufacturer_id'))
                    ->ignore($item->getKey()),
            ],
            'manufacturer_id' => ['required'],
            ...SeoFields::rules(),
        ];
    }
}
