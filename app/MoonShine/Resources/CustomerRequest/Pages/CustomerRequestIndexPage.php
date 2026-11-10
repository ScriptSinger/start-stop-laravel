<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\CustomerRequest\Pages;

use App\Enums\CustomerRequestType;
use App\Jobs\SendCustomerRequestToTelegram;
use App\Models\CustomerRequest;
use App\MoonShine\Resources\Concerns\HasTextFilters;
use App\MoonShine\Resources\CustomerRequest\CustomerRequestResource;
use App\MoonShine\Resources\Product\ProductResource;
use Illuminate\Http\Request;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Http\Responses\MoonShineJsonResponse;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Laravel\QueryTags\QueryTag;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\Ability;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Metrics\Wrapped\Metric;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\DateRange;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Phone;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends IndexPage<CustomerRequestResource>
 */
class CustomerRequestIndexPage extends IndexPage
{
    use HasTextFilters;

    protected bool $isLazy = true;

    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Date::make('Дата', 'created_at')->format('d.m.Y H:i')->sortable(),
            Enum::make('Тип', 'type')->attach(CustomerRequestType::class)->sortable(),
            Text::make('Имя', 'name')->sortable(),
            Phone::make('Телефон', 'phone')->sortable(),
            BelongsTo::make('Товар', 'product', resource: ProductResource::class),
            Text::make('Комментарий', 'comment'),
            Text::make('Telegram', 'telegram_message_id', fn (CustomerRequest $item): string => $item->telegram_message_id !== null ? 'Доставлено' : ($item->telegram_delivery_error ? 'Ошибка доставки' : 'Не доставлено / ожидает отправки')),
            Text::make('Ошибка Telegram', 'telegram_delivery_error'),
            Switcher::make('Обработана', 'is_processed')->updateOnPreview()->sortable(),
        ];
    }

    /**
     * @return ListOf<ActionButtonContract>
     */
    protected function buttons(): ListOf
    {
        return parent::buttons()->add(
            ActionButton::make('Отправить в Telegram')
                ->method('retryTelegram', events: [$this->getResource()->getListEventName()])
                ->canSee(fn (mixed $item): bool => $item instanceof CustomerRequest && $item->telegram_message_id === null)
                ->showInLine(),
        );
    }

    #[AsyncMethod]
    public function retryTelegram(Request $request): MoonShineJsonResponse
    {
        abort_unless(auth(config('moonshine.auth.guard', 'moonshine'))->check(), 403);
        abort_unless($this->getResource()->can(Ability::UPDATE), 403);
        $item = CustomerRequest::query()->findOrFail($request->integer('resourceItem'));
        abort_if(blank(config('services.telegram.bot_token')) || blank(config('services.telegram.chat_id')), 422, 'Настройте бота и группу Telegram.');
        if ($item->telegram_message_id === null) {
            SendCustomerRequestToTelegram::dispatch($item->id)->afterCommit();
        }

        return MoonShineJsonResponse::make()->toast('Отправка запрошена. Обновите список после обработки очереди.')
            ->events([$this->getResource()->getListEventName()]);
    }

    /**
     * @return list<FieldContract>
     */
    protected function filters(): iterable
    {
        return [
            Select::make('Статус', 'is_processed')
                ->options(['0' => 'Новые', '1' => 'Обработанные'])
                ->nullable(),
            Enum::make('Тип', 'type')->attach(CustomerRequestType::class)->nullable(),
            DateRange::make('Дата', 'created_at'),
            $this->containsFilter('Имя', 'name'),
            $this->phoneFilter('Телефон', 'phone'),
            BelongsTo::make('Товар', 'product', resource: ProductResource::class)->nullable()->searchable(),
        ];
    }

    /**
     * @return list<QueryTag>
     */
    protected function queryTags(): array
    {
        return [];
    }

    /**
     * @return list<Metric>
     */
    protected function metrics(): array
    {
        return [];
    }

    /**
     * @param  TableBuilder  $component
     * @return TableBuilder
     */
    protected function modifyListComponent(ComponentContract $component): ComponentContract
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
