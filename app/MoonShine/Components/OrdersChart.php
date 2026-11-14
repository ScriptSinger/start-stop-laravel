<?php

declare(strict_types=1);

namespace App\MoonShine\Components;

use MoonShine\Apexcharts\Components\LineChartMetric;

class OrdersChart extends LineChartMetric
{
    protected function viewData(): array
    {
        $data = parent::viewData();
        $maximum = max(1, ...array_merge(...array_column($data['config']['series'], 'data')));
        $step = max(1, (int) ceil($maximum / 5));
        $axisMaximum = (int) (ceil($maximum / $step) * $step);

        $data['config']['stroke']['curve'] = 'straight';
        $data['config']['yaxis']['title']['text'] = 'Количество, шт.';
        $data['config']['yaxis']['min'] = 0;
        $data['config']['yaxis']['max'] = $axisMaximum;
        $data['config']['yaxis']['tickAmount'] = (int) ($axisMaximum / $step);
        $data['config']['yaxis']['decimalsInFloat'] = 0;
        $data['config']['xaxis']['type'] = 'datetime';
        $data['config']['xaxis']['tickAmount'] = 5;
        $data['config']['xaxis']['labels']['format'] = 'dd.MM';

        return $data;
    }
}
