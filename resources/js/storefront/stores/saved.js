/*
 * Закладки и сравнение без перезагрузки (wishlist / compare темы):
 * кнопки с formaction на /wishlist/… и /compare-products/….
 */
import Alpine from 'alpinejs';
import { postJson } from '../utils';

export default {
    wishlist: 0,
    compare: 0,

    init() {
        const counters = document.querySelector('[data-saved-counts]')?.dataset ?? {};

        this.wishlist = Number(counters.wishlist ?? 0);
        this.compare = Number(counters.compare ?? 0);
    },

    async add(list, button) {
        try {
            const json = await postJson(button.getAttribute('formaction'));

            this[list] = json.count;
            Alpine.store('alerts').show('success', json.message);
        } catch {
            Alpine.store('alerts').show('danger', 'Не получилось. Попробуйте ещё раз.');
        }
    },
};
