<?php

namespace App\MoonShine\Resources\StoreReview\Pages;

use App\Enums\StoreReviewSource;
use App\MoonShine\Fields\ReviewPhoto;
use App\MoonShine\Resources\StoreReview\StoreReviewResource;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/** @extends FormPage<StoreReviewResource> */
class StoreReviewFormPage extends FormPage
{
    protected function fields(): iterable
    {
        return [Box::make([
            ID::make(),
            Text::make('Автор', 'author')->required(),
            Enum::make('Источник', 'source')->attach(StoreReviewSource::class)->required(),
            Number::make('Оценка от 1 до 5', 'rating')->min(1)->max(5)->default(5)->required(),
            Textarea::make('Текст отзыва', 'body')->required(),
            ReviewPhoto::make('Аватар автора', 'avatar_path')->nullable(),
            ReviewPhoto::make('Фотографии к отзыву', 'photo_paths')->multiple()->nullable(),
            Date::make('Дата отзыва', 'reviewed_at')->nullable(),
            Number::make('Порядок', 'sort_order')->min(0)->default(0)->required()
                ->hint('Сначала выводятся отзывы с меньшим числом. На сайте показываются первые 30 опубликованных отзывов.'),
            Switcher::make('Показывать на сайте', 'is_active')->default(false),
        ])];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'author' => ['required', 'string', 'max:100'],
            'source' => ['required', Rule::enum(StoreReviewSource::class)],
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'max:10000'],
            'reviewed_at' => ['nullable', 'date', 'before_or_equal:today'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
