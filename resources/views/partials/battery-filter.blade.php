{{-- Подбор АКБ по марке авто — порт battery_filter.twig старого сайта:
     марка → модель → поколение → страница подбора (компонент batteryWizard). --}}
<div class="battery-wizard-container" id="battery-wizard"
     x-data="batteryWizard({modelsUrl: @js(route('battery-filter.models')), generationsUrl: @js(route('battery-filter.generations')), enginesUrl: @js(route('battery-filter.engines')), resultUrl: @js(route('battery-filter.result'))})"
     x-init="$watch('step', value => $nextTick(() => requestAnimationFrame(() => $el.querySelectorAll('.w-step')[value - 1]?.querySelector('h3')?.focus())))">
    <p x-show="loading" x-cloak role="status">Загружаем данные…</p>
    <p x-show="message" x-cloak role="alert" x-text="message"></p>
    <div class="w-step" x-show="step === 1">
        <div class="w-brand-heading">
            <h3 class="w-title" tabindex="-1">Подбор аккумулятора по марке авто</h3>
            <a class="w-landings" href="{{ route('car-landing.index') }}">Все марки и модели <span aria-hidden="true">→</span></a>
        </div>
        {{-- Одна сетка: остальные марки продолжают ряд популярных. Их
             логотипы грузятся только после «Показать все марки». --}}
        <div class="w-grid brands" id="battery-brand-list">
            @foreach ($popular_brands as $brand)
                <button type="button" class="w-item brand-box" @click="loadModels(@js($brand['name']))">
                    @if ($brand['image'])
                        <img src="{{ $brand['image'] }}" alt="" width="60" height="50">
                    @else
                        <span class="brand-box__letter">{{ mb_substr($brand['name'], 0, 1) }}</span>
                    @endif
                    <span>{{ $brand['name'] }}</span>
                </button>
            @endforeach
            @foreach ($other_brands as $brand)
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
        <h3 class="w-title" tabindex="-1"><button type="button" class="w-back" aria-label="Назад к маркам" @click="cancelRequest(); step = 1">←</button> Модели <span class="w-cur-brand" x-text="brand"></span></h3>
        <div class="w-grid models">
            <template x-for="item in models" :key="item.model">
                <button type="button" class="w-item" @click="loadGenerations(item.model)"><b x-text="item.model"></b></button>
            </template>
        </div>
    </div>

    <div class="w-step" x-show="step === 3" x-cloak>
        <h3 class="w-title" tabindex="-1"><button type="button" class="w-back" aria-label="Назад к моделям" @click="cancelRequest(); step = 2">←</button> Выберите поколение / кузов</h3>
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
        <h3 class="w-title" tabindex="-1"><button type="button" class="w-back" aria-label="Назад к поколениям" @click="cancelRequest(); step = 3">←</button> Выберите двигатель <span class="w-cur-brand" x-text="generation"></span></h3>
        <div class="w-grid engines">
            <template x-for="engine in engines" :key="engine">
                <button type="button" class="w-item" @click="finish(generation, engine)"><b x-text="engine"></b></button>
            </template>
        </div>
    </div>
</div>
