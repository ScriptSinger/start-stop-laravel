@extends('layouts.app')

@php($carName = $subject->fullName())

{{-- Посадочная «Аккумулятор для <модель>»: сверху карточки с параметрами
     машины, ниже подходящие товары на всю ширину, затем поколения и другие
     модели марки. --}}
@section('content')
    <div class="container car-landing">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li><a href="{{ route('car-landing.index') }}">По марке авто</a></li>
                <li><a href="{{ route('car-landing.brand', $brand->slug) }}">Аккумуляторы для {{ $brand->name }}</a></li>
                @if ($generation)
                    <li><a href="{{ $model->url() }}">{{ $model->name }}</a></li>
                    <li>{{ $generation->label }}</li>
                @else
                    <li>{{ $model->name }}</li>
                @endif
            </ul>
            <h1>Аккумулятор для {{ $carName }}</h1>
        </div>

        @if ($generations->isNotEmpty())
            {{-- Переключатель поколений: вся модель или конкретное поколение. --}}
            <nav class="car-landing__generations-nav" aria-label="Поколения {{ $model->fullName() }}">
                <a href="{{ $model->url() }}" @class(['car-landing__chip', 'car-landing__chip_active' => ! $generation])>Все поколения</a>
                @foreach ($generations as $item)
                    <a href="{{ $item->url() }}" @class(['car-landing__chip', 'car-landing__chip_active' => $generation?->slug === $item->slug])>{{ $item->label }}</a>
                @endforeach
            </nav>
        @endif

        @if ($engines->isNotEmpty())
            {{-- Моторам поколения подходят разные АКБ: ниже — варианты для всех,
                 точный подбор — по двигателю. --}}
            <div class="car-landing__engines">
                <span class="car-landing__engines-title">Разным двигателям подходят разные аккумуляторы — уточните свой:</span>
                @foreach ($engines as $engine)
                    <a href="{{ $engine['url'] }}" class="car-landing__chip">{{ $engine['name'] }}</a>
                @endforeach
            </div>
        @endif

        @include('car-landing.partials.summary')

        <div class="uni-wrapper">
            @include('partials.catalog-sorts')

            @include('partials.product-grid', ['columnClass' => 'col-xs-6 col-sm-4 col-md-3 col-lg-3 col-xxl-4'])
        </div>

        @php($generationRows = $model->generationRows())
        @if (! $generation && $generationRows->count() > 1)
            <section class="car-landing__section">
                <h2 class="car-landing__title">Какой аккумулятор нужен {{ $carName }} по годам</h2>
                <div class="table-responsive car-landing__table">
                    <table class="table">
                        <thead>
                            <tr><th>Поколение, двигатель</th><th>Ёмкость, Ач</th><th>Полярность</th><th>Габариты, мм</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($generationRows as $row)
                                <tr>
                                    <td>{{ $row['generation'] }}</td>
                                    <td>{{ $row['capacity'] }}</td>
                                    <td>{{ $row['polarity'] }}</td>
                                    <td>@include('car-landing.partials.dimensions', ['dimensions' => $row['dimensions']])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($otherModels->isNotEmpty())
            <section class="car-landing__section">
                <h2 class="car-landing__title">Аккумуляторы для других моделей {{ $brand->name }}</h2>
                <ul class="car-landing__chips">
                    @foreach ($otherModels as $other)
                        <li><a href="{{ route('car-landing.model', [$brand->slug, $other->slug]) }}" class="car-landing__chip">{{ $other->name }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection
