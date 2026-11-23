@extends('layouts.app')

{{-- Результат подбора: сверху карточки с параметрами машины (как на посадочной
     модели), ниже подходящие товары на всю ширину. --}}
@section('content')
    @php
        $carName = $fitment->displayName();
        $capacities = $fitment->capacities();
        $capacityText = $capacities === [] ? null : (min($capacities) === max($capacities) ? min($capacities) : min($capacities).'–'.max($capacities));
        $polarities = App\Models\BatteryFitment::polarityList([$fitment->polarity]);
        $polarityHints = ['Обратная' => '«+» справа', 'Прямая' => '«+» слева'];
        $dimensions = collect($fitment->dimensions())->map(fn (array $dims) => implode('×', $dims))->all();
        $total = $products->total();
    @endphp
    <div class="container car-landing" x-data x-init="$store.garage.save(@js($carName), @js(request()->fullUrlWithoutQuery(['sort', 'limit', 'page'])))">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb mobile">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li><a href="{{ route('home') }}#battery-wizard">Подбор АКБ</a></li>
                <li>{{ $carName }}</li>
            </ul>
            <h1>Аккумуляторы для {{ $carName }}</h1>
        </div>

        {{-- Параметры, по которым шёл подбор, — карточками, как на посадочной модели. --}}
        <div class="car-landing__summary">
            <div class="car-landing__specs">
                @if ($capacityText !== null)
                    <div class="car-landing__spec">
                        <i class="car-landing__spec-icon fas fa-car-battery"></i>
                        <div class="car-landing__spec-value">{{ $capacityText }} Ач</div>
                        <div class="car-landing__spec-label">Ёмкость</div>
                    </div>
                @endif
                @if ($polarities !== [])
                    <div class="car-landing__spec">
                        <div class="car-landing__terminals {{ $polarities === ['Прямая'] ? 'car-landing__terminals_direct' : '' }}" aria-hidden="true">
                            <span class="car-landing__terminal car-landing__terminal_minus">−</span>
                            <span class="car-landing__terminal car-landing__terminal_plus">+</span>
                        </div>
                        <div class="car-landing__spec-value">{{ implode(' / ', $polarities) }}</div>
                        <div class="car-landing__spec-label">Полярность{{ count($polarities) === 1 && isset($polarityHints[$polarities[0]]) ? ', '.$polarityHints[$polarities[0]] : '' }}</div>
                    </div>
                @endif
                @if ($fitment->terminalsLabel())
                    <div class="car-landing__spec">
                        <i class="car-landing__spec-icon fas fa-plug"></i>
                        <div class="car-landing__spec-value">{{ $fitment->terminalsLabel() }}</div>
                        <div class="car-landing__spec-label">Клеммы</div>
                    </div>
                @endif
                @if ($dimensions !== [])
                    <div class="car-landing__spec">
                        <i class="car-landing__spec-icon fas fa-ruler-combined"></i>
                        @include('car-landing.partials.dimensions', ['dimensions' => $dimensions, 'collapsible' => true])
                        <div class="car-landing__spec-label">Габариты Д×Ш×В, мм</div>
                    </div>
                @endif
                <div class="car-landing__spec car-landing__spec_accent">
                    <i class="car-landing__spec-icon fas fa-tags"></i>
                    <div class="car-landing__spec-value">{{ $total }} <x-plural :count="$total" forms="вариант|варианта|вариантов" /></div>
                    <div class="car-landing__spec-label">подходят вашей машине</div>
                </div>
            </div>

            <ul class="car-landing__perks">
                @if ($landing)
                    <li><a href="{{ route('car-landing.model', [$landing->brand->slug, $landing->slug]) }}">Все аккумуляторы для {{ $landing->fullName() }} <span aria-hidden="true">→</span></a></li>
                @endif
                <li><i class="fas fa-recycle"></i> Скидка за старый аккумулятор по трейд-ину</li>
                <li class="car-landing__perks-action"><button type="button" class="btn car-landing__other" data-toggle="modal" data-target="#modal-battery-wizard"><i class="fas fa-car" aria-hidden="true"></i> Другая машина</button></li>
            </ul>
        </div>

        @include('partials.battery-wizard-modal')

        <div class="uni-wrapper">
            @if ($products->isEmpty())
                <div class="div-text-empty">
                    Подходящих аккумуляторов сейчас нет в наличии. Позвоните нам —
                    <a href="tel:+{{ preg_replace('/\D/', '', config('shop.phone')) }}">{{ config('shop.phone') }}</a>, подберём под заказ.
                </div>
            @else
                @include('partials.catalog-sorts')

                @include('partials.product-grid', ['columnClass' => 'col-xs-6 col-sm-4 col-md-3 col-lg-3 col-xxl-4'])
            @endif
        </div>
    </div>
@endsection
