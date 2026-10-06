/*
 * Корзина в шапке и окно «Корзина» (cart.add / uniCartUpd темы).
 * Начальное состояние — из разметки шапки, дальше — из ответов сервера:
 * {count, products, html} (CartController::miniCart()).
 */
import $ from 'jquery';
import Alpine from 'alpinejs';
import { postJson } from '../utils';

export default {
    count: 0,
    products: [],
    html: '',

    init() {
        const dropdown = document.querySelector('[data-mini-cart-html]');

        this.html = dropdown?.innerHTML ?? '';
        this.count = Number(dropdown?.dataset.count ?? 0);
        this.products = JSON.parse(dropdown?.dataset.products || '[]');
    },

    has(productId) {
        return this.products.includes(productId);
    },

    open() {
        $('#modal-cart').modal('show');
    },

    /** «В корзину» из карточки или со страницы товара. */
    async add(form) {
        try {
            this.update(await postJson(form.action, new FormData(form)));
            this.open();
        } catch {
            Alpine.store('alerts').show('danger', 'Не получилось добавить в корзину. Попробуйте ещё раз.');
        }
    },

    /** Количество и удаление в окне «Корзина». */
    async change(form) {
        try {
            const json = await postJson(form.action, new FormData(form));

            // На странице оформления пересчитать надо и её.
            if (document.querySelector('[data-checkout]')) {
                location.reload();

                return;
            }

            this.update(json);
        } catch {
            Alpine.store('alerts').show('danger', 'Не получилось. Попробуйте ещё раз.');
        }
    },

    update({count, products, html}) {
        this.count = count;
        this.products = products;
        this.html = html;
    },
};
