{{-- Подбор АКБ по марке авто — порт battery_filter старого сайта:
     марка → модель → поколение (→ двигатель) → страница подбора (компонент batteryWizard).
     Заголовок один на все шаги, ход подбора показывает полоса шагов: пройденный шаг
     подписан выбранным значением и возвращает назад. Заголовки шагов — для скринридера. --}}
@php($phoneHref = 'tel:+'.preg_replace('/\D/', '', config('shop.phone')))
<div class="battery-wizard-container" id="battery-wizard"
     x-data="batteryWizard({modelsUrl: @js(route('battery-filter.models')), generationsUrl: @js(route('battery-filter.generations')), enginesUrl: @js(route('battery-filter.engines')), resultUrl: @js(route('battery-filter.result'))})"
     x-init="$watch('step', value => $nextTick(() => requestAnimationFrame(() => $el.querySelectorAll('.w-step')[value - 1]?.querySelector('h3')?.focus())))">
    <div class="w-progress" x-show="loading" x-cloak role="status" aria-label="Загружаем данные"></div>

    <div class="w-brand-heading">
        <div>
            <span class="section-heading__eyebrow">Быстрый подбор</span>
            <h2 class="w-title w-title--section">Аккумулятор для вашего автомобиля</h2>
        </div>
        <a class="w-landings" href="{{ route('car-landing.index') }}">Все марки и модели <span aria-hidden="true">→</span></a>
    </div>

    <ol class="w-stepper" aria-label="Шаги подбора">
        <li class="w-stepper__item" :class="{'is-current': step === 1, 'is-done': step > 1}">
            <button type="button" class="w-stepper__btn" :disabled="step === 1" :aria-current="step === 1 ? 'step' : null" @click="goTo(1)">
                <span class="w-stepper__num">01</span>
                <span class="w-stepper__text"><span class="w-stepper__label">Марка</span><span class="w-stepper__value" x-show="step > 1" x-cloak x-text="brand"></span></span>
            </button>
        </li>
        <li class="w-stepper__item" :class="{'is-current': step === 2, 'is-done': step > 2}">
            <button type="button" class="w-stepper__btn" :disabled="step <= 2" :aria-current="step === 2 ? 'step' : null" @click="goTo(2)">
                <span class="w-stepper__num">02</span>
                <span class="w-stepper__text"><span class="w-stepper__label">Модель</span><span class="w-stepper__value" x-show="step > 2" x-cloak x-text="model"></span></span>
            </button>
        </li>
        <li class="w-stepper__item" :class="{'is-current': step === 3, 'is-done': step > 3}">
            <button type="button" class="w-stepper__btn" :disabled="step <= 3" :aria-current="step === 3 ? 'step' : null" @click="goTo(3)">
                <span class="w-stepper__num">03</span>
                <span class="w-stepper__text"><span class="w-stepper__label">Поколение</span><span class="w-stepper__value" x-show="step > 3" x-cloak x-text="generation"></span></span>
            </button>
        </li>
        <li class="w-stepper__item is-current" x-show="step === 4" x-cloak>
            <span class="w-stepper__btn" aria-current="step">
                <span class="w-stepper__num">04</span>
                <span class="w-stepper__text"><span class="w-stepper__label">Двигатель</span></span>
            </span>
        </li>
    </ol>

    <p class="w-message" x-show="message" x-cloak role="alert" x-text="message"></p>

    <div class="w-step" x-show="step === 1">
        <h3 class="sr-only" tabindex="-1">Выберите марку</h3>
        {{-- Одна сетка: остальные марки продолжают ряд популярных. Их
             логотипы грузятся только после «Показать все марки». --}}
        <div class="w-grid brands" id="battery-brand-list">
            {{-- Первые десять марок — два ровных ряда по пять; остальные открываются кнопкой. --}}
            @foreach (array_slice($popular_brands, 0, 10) as $brand)
                <button type="button" class="w-item brand-box" @click="loadModels(@js($brand['name']))">
                    @if ($brand['image'])
                        <img src="{{ $brand['image'] }}" alt="" width="60" height="50">
                    @else
                        <span class="brand-box__letter">{{ mb_substr($brand['name'], 0, 1) }}</span>
                    @endif
                    <span>{{ $brand['name'] }}</span>
                </button>
            @endforeach
            @foreach ([...array_slice($popular_brands, 10), ...$other_brands] as $brand)
                <button type="button" class="w-item brand-box" x-show="allBrands" x-cloak @click="loadModels(@js($brand['name']))">
                    @if ($brand['image'])
                        <img :src="allBrands && @js($brand['image'])" alt="" width="60" height="50">
                    @else
                        <span class="brand-box__letter">{{ mb_substr($brand['name'], 0, 1) }}</span>
                    @endif
                    <span>{{ $brand['name'] }}</span>
                </button>
            @endforeach
        </div>

        <div class="w-brand-actions">
            <button type="button" class="w-btn-more" aria-controls="battery-brand-list" :aria-expanded="allBrands.toString()" @click="allBrands = !allBrands"><span x-text="allBrands ? 'Скрыть марки' : 'Показать все марки'">Показать все марки</span></button>
        </div>
    </div>

    <div class="w-step" x-show="step === 2" x-cloak>
        <h3 class="sr-only" tabindex="-1">Выберите модель</h3>
        <div class="w-grid models">
            <template x-for="item in models" :key="item.model">
                <button type="button" class="w-item" @click="loadGenerations(item.model)"><b x-text="item.model"></b></button>
            </template>
        </div>
    </div>

    <div class="w-step" x-show="step === 3" x-cloak>
        <h3 class="sr-only" tabindex="-1">Выберите поколение или кузов</h3>
        <div class="w-grid gens">
            <template x-for="item in generations" :key="item.name">
                <button type="button" class="w-item" @click="choose(item)">
                    <img :src="item.image" :alt="item.name">
                    <span x-text="item.name"></span>
                </button>
            </template>
        </div>
    </div>

    {{-- Только когда разным двигателям поколения нужны разные аккумуляторы. --}}
    <div class="w-step" x-show="step === 4" x-cloak>
        <h3 class="sr-only" tabindex="-1">Выберите двигатель</h3>
        <div class="w-grid engines">
            <template x-for="engine in engines" :key="engine">
                <button type="button" class="w-item" @click="finish(generation, engine)"><b x-text="engine"></b></button>
            </template>
        </div>
    </div>

    <p class="w-help" x-show="step > 1" x-cloak>Не нашли свою машину? Позвоните <a href="{{ $phoneHref }}">{{ config('shop.phone') }}</a> — подберём вручную.</p>
</div>
