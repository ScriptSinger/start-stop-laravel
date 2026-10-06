/*
 * Окно с формой (заказ звонка, быстрый заказ, вопрос о товаре) — разметка
 * uniModalWindow темы. Без JavaScript ссылки ведут на страницы с теми же формами.
 */
import $ from 'jquery';
import Alpine from 'alpinejs';

export default {
    title: '',
    html: '',

    async open(url, title) {
        try {
            const response = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}});

            this.title = title;
            this.html = await response.text();
            $('#modal-form').modal('show');
        } catch {
            Alpine.store('alerts').show('danger', 'Не получилось открыть форму. Попробуйте ещё раз.');
        }
    },
};
