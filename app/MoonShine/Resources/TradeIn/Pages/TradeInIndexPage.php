<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\TradeIn\Pages;

use App\Models\TradeInType;
use App\MoonShine\Fields\Money;
use App\MoonShine\Resources\TradeIn\TradeInResource;
use Illuminate\Contracts\Database\Eloquent\Builder;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\Enums\Ability;
use MoonShine\Support\Enums\Color;
use MoonShine\UI\Components\Badge;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<TradeInResource>
 */
class TradeInIndexPage extends IndexPage
{
    protected bool $isLazy = true;

    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        $canUpdate = fn (): bool => $this->getResource()->can(Ability::UPDATE);

        return [
            Text::make('Название для сайта', 'name')->sortable(),
            Text::make('Код типа', 'code')->sortable(),
            // Сумма и «Активен» меняются прямо в списке; сумма действует сразу на все подходящие товары.
            Money::make('Скидка за старый АКБ, ₽', 'amount')
                ->nullable()
                ->updateOnPreview(condition: $canUpdate)
                ->sortable(),
            // Диапазон размеров корпуса; у грузовых (Груз A/B/C) размеров нет — ячейка пустая, тип берётся по коду в «Габаритах».
            // Каждый диапазон (длина, ширина, высота) своим блоком: [185–189] × [125–129] × [225–229].
            Preview::make('Диапазон размеров, мм (Д × Ш × В)', 'id', fn (TradeInType $type): string => $type->hasDimensionRange()
                ? implode(' × ', [
                    (string) Badge::make("{$type->length_min}–{$type->length_max}", Color::PURPLE),
                    (string) Badge::make("{$type->width_min}–{$type->width_max}", Color::PURPLE),
                    (string) Badge::make("{$type->height_min}–{$type->height_max}", Color::PURPLE),
                ])
                : '')
                // По длине; типы без размеров (грузовые) в конце.
                ->sortable(fn (Builder $query, string $column, string $direction): Builder => $query
                    ->orderByRaw('length_min IS NULL')
                    ->orderBy('length_min', $direction)),
            Number::make('Порядок', 'sort_order')->sortable(),
            Switcher::make('Активен', 'is_active')->updateOnPreview(condition: $canUpdate)->sortable(),
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function filters(): iterable
    {
        return [
            Select::make('Скидка', 'discount')
                ->options(['set' => 'Задана', 'empty' => 'Не задана'])
                ->nullable()
                ->onApply(fn (Builder $query, mixed $value): Builder => match ($value) {
                    'set' => $query->where('amount', '>', 0),
                    'empty' => $query->where(fn (Builder $query) => $query->whereNull('amount')->orWhere('amount', '<=', 0)),
                    default => $query,
                }),
            Select::make('Активен', 'activity')
                ->options(['1' => 'Да', '0' => 'Нет'])
                ->nullable()
                ->onApply(fn (Builder $query, mixed $value): Builder => in_array($value, ['0', '1'], true)
                    ? $query->where('is_active', (bool) $value)
                    : $query),
            Select::make('Диапазон размеров', 'sizes')
                ->options(['set' => 'Задан', 'none' => 'Нет (по коду в «Габаритах»)'])
                ->nullable()
                ->onApply(fn (Builder $query, mixed $value): Builder => match ($value) {
                    'set' => $query->whereNotNull('length_min'),
                    'none' => $query->whereNull('length_min'),
                    default => $query,
                }),
            Number::make('Скидка от, ₽', 'amount_from')
                ->nullable()
                ->onApply(fn (Builder $query, mixed $value): Builder => is_numeric($value) ? $query->where('amount', '>=', (float) $value) : $query),
            Number::make('Скидка до, ₽', 'amount_to')
                ->nullable()
                ->onApply(fn (Builder $query, mixed $value): Builder => is_numeric($value) ? $query->where('amount', '<=', (float) $value) : $query),
        ];
    }
}
