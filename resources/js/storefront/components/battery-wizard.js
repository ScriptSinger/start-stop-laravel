/*
 * Подбор АКБ по марке авто: марка → модель → поколение → страница подбора.
 * Порт модуля battery_filter старого сайта; данные — BatteryFilterController.
 */
import Alpine from 'alpinejs';

export default ({modelsUrl, generationsUrl, enginesUrl, resultUrl}) => ({
    step: 1,
    allBrands: false,
    brand: '',
    model: '',
    generation: '',
    models: [],
    generations: [],
    engines: [],

    async loadModels(brand) {
        const models = await this.fetchJson(modelsUrl, {brand});

        if (models.length) {
            this.brand = brand;
            this.models = models;
            this.step = 2;
        }
    },

    async loadGenerations(model) {
        const generations = await this.fetchJson(generationsUrl, {brand: this.brand, model});

        // У модели с посадочной страницей и одним поколением выбирать нечего.
        if (generations.length === 1 && generations[0].url) {
            location.href = generations[0].url;

            return;
        }

        if (generations.length) {
            this.model = model;
            this.generations = generations;
            this.step = 3;
        }
    },

    // Если моторам поколения нужны разные АКБ — ещё шаг «двигатель»;
    // у поколений с посадочной страницей есть готовая ссылка (url).
    async choose(generation) {
        if (generation.engines) {
            const engines = await this.fetchJson(enginesUrl, {brand: this.brand, model: this.model, gen: generation.name});

            if (engines.length) {
                this.generation = generation.name;
                this.engines = engines;
                this.step = 4;

                return;
            }
        }

        if (generation.url) {
            location.href = generation.url;
        } else {
            this.finish(generation.name);
        }
    },

    async finish(generation, engine = '') {
        const json = await this.fetchJson(resultUrl, {brand: this.brand, model: this.model, gen: generation, engine});

        if (json.redirect) {
            location.href = json.redirect;
        } else {
            Alpine.store('alerts').show('danger', 'К сожалению, параметры для этой модификации не найдены.');
        }
    },

    async fetchJson(url, params) {
        const response = await fetch(`${url}?${new URLSearchParams(params)}`, {headers: {'Accept': 'application/json'}});

        return response.ok ? response.json() : [];
    },
});
