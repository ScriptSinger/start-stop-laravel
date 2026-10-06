{{-- Подбор АКБ по марке авто — порт battery_filter.twig старого сайта:
     марка → модель → поколение → страница подбора (компонент batteryWizard). --}}
<div class="battery-wizard-container" id="battery-wizard"
     x-data="batteryWizard({modelsUrl: @js(route('battery-filter.models')), generationsUrl: @js(route('battery-filter.generations')), enginesUrl: @js(route('battery-filter.engines')), resultUrl: @js(route('battery-filter.result'))})">
    <div class="w-step" x-show="step === 1">
        <h3 class="w-title">Подбор аккумулятора по марке авто</h3>
        <div class="w-grid brands">
            @foreach ($popular_brands as $brand)
                <div class="w-item brand-box" @click="loadModels(@js($brand['name']))">
                    <img src="{{ $brand['image'] }}" alt="{{ $brand['name'] }}">
                    <span>{{ $brand['name'] }}</span>
                </div>
            @endforeach
        </div>

        <div class="w-grid brands mt-3" x-show="allBrands" x-cloak>
            @foreach ($other_brands as $brand)
                <div class="w-item brand-box" @click="loadModels(@js($brand['name']))">
                    <img src="{{ $brand['image'] }}" alt="{{ $brand['name'] }}" loading="lazy">
                    <span>{{ $brand['name'] }}</span>
                </div>
            @endforeach
        </div>

        <div class="text-center">
            <button type="button" class="w-btn-more" x-show="!allBrands" @click="allBrands = true">Показать все марки</button>
        </div>

        <div class="w-landings">
            <a href="{{ route('car-landing.index') }}">Аккумуляторы для всех марок и моделей →</a>
        </div>
    </div>

    <div class="w-step" x-show="step === 2" x-cloak>
        <h3 class="w-title"><span class="w-back" @click="step = 1">←</span> Модели <span class="w-cur-brand" x-text="brand"></span></h3>
        <div class="w-grid models">
            <template x-for="item in models" :key="item.model">
                <div class="w-item" @click="loadGenerations(item.model)"><b x-text="item.model"></b></div>
            </template>
        </div>
    </div>

    <div class="w-step" x-show="step === 3" x-cloak>
        <h3 class="w-title"><span class="w-back" @click="step = 2">←</span> Выберите поколение / кузов</h3>
        <div class="w-grid gens">
            <template x-for="item in generations" :key="item.name">
                <div class="w-item" @click="choose(item)">
                    <img :src="item.image" :alt="item.name">
                    <div x-text="item.name"></div>
                </div>
            </template>
        </div>
    </div>

    {{-- Только когда разным двигателям поколения нужны разные аккумуляторы. --}}
    <div class="w-step" x-show="step === 4" x-cloak>
        <h3 class="w-title"><span class="w-back" @click="step = 3">←</span> Выберите двигатель <span class="w-cur-brand" x-text="generation"></span></h3>
        <div class="w-grid engines">
            <template x-for="engine in engines" :key="engine">
                <div class="w-item" @click="finish(generation, engine)"><b x-text="engine"></b></div>
            </template>
        </div>
    </div>
</div>
