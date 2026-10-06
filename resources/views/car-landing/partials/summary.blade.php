{{-- Карточки параметров машины, короткий текст из данных подбора и
     преимущества магазина. Текст отличает страницу от соседних моделей. --}}
@php
    $capacities = $subject->capacities();
    $capacityText = $capacities === [] ? null : (min($capacities) === max($capacities) ? min($capacities) : min($capacities).'–'.max($capacities));
    $polarities = $subject->polarities();
    $polarityHints = ['Обратная' => '«+» справа', 'Прямая' => '«+» слева'];
    $polarityCases = ['Обратная' => 'обратной', 'Прямая' => 'прямой', 'Универсальная' => 'универсальной'];
    $dimensions = $subject->dimensions();
    $total = $products->total();
    $phoneHref = 'tel:+'.preg_replace('/\D/', '', config('shop.phone'));
@endphp
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
                {{-- Схема клемм: «+» слева при прямой полярности, справа — при обратной. --}}
                <div class="car-landing__terminals {{ $polarities === ['Прямая'] ? 'car-landing__terminals_direct' : '' }}" aria-hidden="true">
                    <span class="car-landing__terminal car-landing__terminal_minus">−</span>
                    <span class="car-landing__terminal car-landing__terminal_plus">+</span>
                </div>
                <div class="car-landing__spec-value">{{ implode(' / ', $polarities) }}</div>
                <div class="car-landing__spec-label">Полярность{{ count($polarities) === 1 && isset($polarityHints[$polarities[0]]) ? ', '.$polarityHints[$polarities[0]] : '' }}</div>
            </div>
        @endif
        @if ($dimensions !== [])
            <div class="car-landing__spec">
                <i class="car-landing__spec-icon fas fa-ruler-combined"></i>
                @include('car-landing.partials.dimensions', ['dimensions' => $dimensions])
                <div class="car-landing__spec-label">Габариты Д×Ш×В, мм</div>
            </div>
        @endif
        <div class="car-landing__spec car-landing__spec_accent">
            <i class="car-landing__spec-icon fas fa-tags"></i>
            <div class="car-landing__spec-value">{{ $priceFrom !== null ? 'от '.number_format($priceFrom, 0, '', ' ').' ₽' : $total }}</div>
            <div class="car-landing__spec-label">
                {{ $total }} <x-plural :count="$total" forms="вариант|варианта|вариантов" />{{ $inStockCount > 0 ? ', '.$inStockCount.' в наличии' : '' }}
            </div>
        </div>
    </div>

    <p class="car-landing__text">
        Для {{ $subject->fullName() }} подходят аккумуляторы
        {{ collect([
            $capacityText === null ? null : 'ёмкостью '.$capacityText.' Ач',
            $polarities === [] ? null : 'с '.collect($polarities)->map(fn (string $polarity) => $polarityCases[$polarity] ?? mb_strtolower($polarity))->implode(' или ').' полярностью',
            $subject->lengthRange() === null ? null : 'длиной корпуса '.$subject->lengthRange().' мм и высотой до '.$subject->maxHeight().' мм',
        ])->filter()->implode(', ') }}.
        Ниже — все подходящие модели с ценами и наличием в Уфе.
    </p>

    <ul class="car-landing__perks">
        <li><i class="fas fa-shipping-fast"></i> Доставка по Уфе и самовывоз</li>
        <li><i class="fas fa-recycle"></i> Скидка за старый аккумулятор по трейд-ину</li>
        <li><i class="fas fa-phone"></i> Поможем с выбором: <a href="{{ $phoneHref }}">{{ config('shop.phone') }}</a></li>
        <li class="car-landing__perks-action"><a href="{{ route('home') }}#battery-wizard" class="btn btn-default">Другая машина</a></li>
    </ul>
</div>
