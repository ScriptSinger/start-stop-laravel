<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\BatteryFitment;

use App\Models\BatteryFitment;
use App\MoonShine\Resources\BatteryFitment\Pages\BatteryFitmentDetailPage;
use App\MoonShine\Resources\BatteryFitment\Pages\BatteryFitmentFormPage;
use App\MoonShine\Resources\BatteryFitment\Pages\BatteryFitmentIndexPage;
use App\MoonShine\Resources\Concerns\ResetsPageOutOfRange;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\SortDirection;

/**
 * @extends ModelResource<BatteryFitment, BatteryFitmentIndexPage, BatteryFitmentFormPage, BatteryFitmentDetailPage>
 */
class BatteryFitmentResource extends ModelResource
{
    use ResetsPageOutOfRange;

    protected string $model = BatteryFitment::class;

    protected string $title = 'База АКБ по авто';

    protected string $column = 'brand';

    // По алфавиту: марка → модель → поколение, а не в порядке загрузки.
    protected string $sortColumn = 'brand';

    protected SortDirection $sortDirection = SortDirection::ASC;

    /**
     * @return string[]
     */
    protected function search(): array
    {
        return ['brand', 'model', 'generation', 'engine'];
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            BatteryFitmentIndexPage::class,
            BatteryFitmentFormPage::class,
            BatteryFitmentDetailPage::class,
        ];
    }
}
