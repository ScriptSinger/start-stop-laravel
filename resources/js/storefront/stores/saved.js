/*
 * Закладки и сравнение без перезагрузки (wishlist / compare и uniChangeBtn темы):
 * кнопки с formaction на /wishlist/… и /compare-products/…. Товар в списке —
 * кнопка отмечена («В закладках»), повторное нажатие убирает его из списка.
 */
import Alpine from 'alpinejs';
import { postJson } from '../utils';

export default {
    wishlist: 0,
    compare: 0,
    ids: {wishlist: [], compare: []},

    init() {
        const data = document.querySelector('[data-saved-counts]')?.dataset ?? {};

        this.wishlist = Number(data.wishlist ?? 0);
        this.compare = Number(data.compare ?? 0);
        this.ids = {
            wishlist: JSON.parse(data.wishlistIds || '[]'),
            compare: JSON.parse(data.compareIds || '[]'),
        };
    },

    has(list, productId) {
        return this.ids[list].includes(productId);
    },

    async toggle(list, productId, button) {
        try {
            const body = this.has(list, productId) ? {_method: 'DELETE'} : {};
            const json = await postJson(button.getAttribute('formaction'), body);

            this[list] = json.count;
            this.ids[list] = json.ids;
            Alpine.store('alerts').show('success', json.message);
        } catch {
            Alpine.store('alerts').show('danger', 'Не получилось. Попробуйте ещё раз.');
        }
    },
};
