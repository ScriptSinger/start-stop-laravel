<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\BatteryFitment;

use Illuminate\Database\Eloquent\Model;
use App\Models\BatteryFitment;
use App\MoonShine\Resources\BatteryFitment\Pages\BatteryFitmentIndexPage;
use App\MoonShine\Resources\BatteryFitment\Pages\BatteryFitmentFormPage;
use App\MoonShine\Resources\BatteryFitment\Pages\BatteryFitmentDetailPage;

use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Contracts\Core\PageContract;

/**
 * @extends ModelResource<BatteryFitment, BatteryFitmentIndexPage, BatteryFitmentFormPage, BatteryFitmentDetailPage>
 */
class BatteryFitmentResource extends ModelResource
{
    protected string $model = BatteryFitment::class;

    protected string $title = 'База АКБ по авто';

    protected string $column = 'brand';
    
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
