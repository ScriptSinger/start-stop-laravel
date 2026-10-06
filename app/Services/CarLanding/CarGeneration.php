<?php

namespace App\Services\CarLanding;

use App\Models\BatteryFitment;
use Illuminate\Support\Collection;

/**
 * Поколение модели (у части моделей в данных вместо поколений — моторы):
 * все записи подбора с этой подписью.
 */
final readonly class CarGeneration
{
    use DescribesFitments;

    /**
     * @param  Collection<int, BatteryFitment>  $fitments
     */
    public function __construct(
        public CarModel $model,
        public string $slug,
        public string $label,
        public Collection $fitments,
    ) {}

    /**
     * «Lada Vesta I Рестайлинг 2022 - н.в.».
     */
    public function fullName(): string
    {
        return $this->model->fullName().' '.$this->label;
    }

    public function url(): string
    {
        return route('car-landing.generation', [$this->model->brand->slug, $this->model->slug, $this->slug]);
    }

    public function key(): string
    {
        return $this->model->key().'/'.$this->slug;
    }
}
