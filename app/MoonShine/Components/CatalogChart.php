<?php

declare(strict_types=1);

namespace App\MoonShine\Components;

use MoonShine\Apexcharts\Components\DonutChartMetric;

class CatalogChart extends DonutChartMetric
{
    protected function viewData(): array
    {
        $data = parent::viewData();
        $data['config']['plotOptions']['pie']['donut']['labels']['total']['label'] = 'Всего позиций';

        return $data;
    }
}
