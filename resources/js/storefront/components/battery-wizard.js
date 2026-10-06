/*
 * Подбор АКБ по марке авто: марка → модель → поколение → страница подбора.
 * Порт модуля battery_filter старого сайта; данные — BatteryFilterController.
 */
import Alpine from 'alpinejs';

export default ({modelsUrl, generationsUrl, resultUrl}) => ({
    step: 1,
    allBrands: false,
    brand: '',
    model: '',
    models: [],
    generations: [],

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

    // У поколений с посадочной страницей есть готовая ссылка (url).
    choose(generation) {
        if (generation.url) {
            location.href = generation.url;
        } else {
            this.finish(generation.name);
        }
    },

    async finish(generation) {
        const json = await this.fetchJson(resultUrl, {brand: this.brand, model: this.model, gen: generation});

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
