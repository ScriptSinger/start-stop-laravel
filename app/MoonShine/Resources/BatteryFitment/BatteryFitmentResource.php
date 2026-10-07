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

/**
 * @extends ModelResource<BatteryFitment, BatteryFitmentIndexPage, BatteryFitmentFormPage, BatteryFitmentDetailPage>
 */
class BatteryFitmentResource extends ModelResource
{
    use ResetsPageOutOfRange;

    protected string $model = BatteryFitment::class;

    protected string $title = 'База АКБ по авто';

    protected string $column = 'brand';

    /**
     * @return string[]
     */
    protected function search(): array
    {
        return ['brand', 'model', 'generation'];
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
