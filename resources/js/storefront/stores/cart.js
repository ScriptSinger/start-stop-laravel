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
    opener: null,
    busy: false,
    syncPending: false,
    stateUrl: '',

    init() {
        $('#modal-cart').on('hidden.bs.modal', () => {
            this.opener?.focus();
            this.opener = null;
        });
        const dropdown = document.querySelector('[data-mini-cart-html]');

        this.stateUrl = dropdown?.dataset.stateUrl ?? '';
        window.addEventListener('storage', (event) => {
            if (event.key === 'storefront-cart-changed') this.syncFromServer();
        });
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') this.syncFromServer();
        });

        this.html = dropdown?.innerHTML ?? '';
        this.count = Number(dropdown?.dataset.count ?? 0);
        this.products = JSON.parse(dropdown?.dataset.products || '[]');
    },

    has(productId) {
        return this.products.includes(productId);
    },

    setBusy(busy) {
        this.busy = busy;
        document.querySelectorAll('[data-cart-progress]').forEach((element) => {
            element.style.display = busy ? '' : 'none';
        });
    },

    open() {
        if (!$('#modal-cart').hasClass('in') && !this.opener) {
            this.opener = document.activeElement;
        }
        $('#modal-cart').modal('show');
    },

    /** «В корзину» из карточки или со страницы товара. */
    async add(form) {
        if (this.busy) return;

        this.opener = document.activeElement;
        this.setBusy(true);
        const buttons = [...form.querySelectorAll('button[type="submit"]')];
        buttons.forEach((button) => { button.disabled = true; });
        try {
            this.update(await postJson(form.action, new FormData(form)));
            this.notifyOtherTabs();
            if (document.querySelector('[data-checkout]')) await this.refreshCheckout();
            this.open();
        } catch {
            this.notifyOtherTabs();
            this.syncPending = true;
            Alpine.store('alerts').show('danger', 'Не удалось подтвердить добавление. Проверьте корзину перед повторной попыткой.');
        } finally {
            this.setBusy(false);
            buttons.forEach((button) => { button.disabled = false; });
            if (this.syncPending) this.syncFromServer();
        }
    },

    /** Количество и удаление в окне «Корзина». */
    async change(form) {
        if (this.busy) return;

        const body = new FormData(form);
        this.setBusy(true);
        const controls = [...document.querySelectorAll('#modal-cart .header-cart__item button, #modal-cart .header-cart__item input, #unicart button, #unicart input, #confirm_checkout')];
        controls.forEach((control) => { control.disabled = true; });
        let saved = false;
        try {
            const json = await postJson(form.action, body);
            saved = true;
            this.update(json);
            this.notifyOtherTabs();
            if (document.querySelector('[data-checkout]')) await this.refreshCheckout();
        } catch (error) {
            if (!saved) {
                this.notifyOtherTabs();
                this.syncPending = true;
                const previous = this.html;
                this.html = '';
                await Alpine.nextTick();
                this.html = previous;
                if (document.querySelector('[data-checkout]')) {
                    try {
                        await this.refreshCheckout();
                    } catch {
                        saved = true;
                    }
                }
            }
            Alpine.store('alerts').show('danger', error.response?.status === 409
                ? 'Корзина изменилась в другой вкладке. Проверьте количество и повторите действие.' : saved
                ? 'Корзина изменена, но обновить оформление не удалось. Обновите страницу перед заказом.'
                : 'Не удалось подтвердить изменение. Проверьте актуальное состояние корзины перед повторной попыткой.');
            if (saved) {
                const confirm = document.querySelector('#confirm_checkout');
                if (confirm) {
                    confirm.dataset.cartError = '1';
                    confirm.disabled = true;
                }
            }
        } finally {
            this.setBusy(false);
            controls.forEach((control) => { if (control.id !== 'confirm_checkout' || !control.hasAttribute('data-cart-error')) control.disabled = false; });
            if (this.syncPending) this.syncFromServer();
        }
    },

    notifyOtherTabs() {
        try {
            localStorage.setItem('storefront-cart-changed', `${Date.now()}-${Math.random()}`);
        } catch {
            // The cart remains usable when browser storage is unavailable.
        }
    },

    async syncFromServer() {
        if (this.busy) {
            this.syncPending = true;
            return;
        }
        if (!this.stateUrl) return;

        this.syncPending = false;
        this.setBusy(true);
        const controls = [...document.querySelectorAll('#modal-cart .header-cart__item button, #modal-cart .header-cart__item input, #unicart button, #unicart input, #confirm_checkout')];
        controls.forEach((control) => { control.disabled = true; });
        try {
            const response = await fetch(this.stateUrl, {headers: {'Accept': 'application/json'}, cache: 'no-store'});
            if (!response.ok) throw new Error('Cart sync failed');
            this.update(await response.json());
            if (document.querySelector('[data-checkout]')) await this.refreshCheckout();
        } catch {
            const confirm = document.querySelector('#confirm_checkout');
            if (confirm) {
                confirm.dataset.cartError = '1';
                confirm.disabled = true;
            }
            Alpine.store('alerts').show('danger', 'Не удалось обновить корзину после изменений в другой вкладке. Обновите страницу.');
        } finally {
            this.setBusy(false);
            controls.forEach((control) => { if (control.id !== 'confirm_checkout' || !control.hasAttribute('data-cart-error')) control.disabled = false; });
            if (this.syncPending) this.syncFromServer();
        }
    },

    async refreshCheckout() {
        const response = await fetch(window.location.pathname, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
        if (!response.ok) throw new Error('Checkout refresh failed');
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        const warnings = [...page.querySelectorAll('[data-cart-unavailable], [data-cart-price-changed]')]
            .map((element) => element.textContent.trim());
        if (warnings.length) Alpine.store('alerts').show('warning', warnings.join(' '));
        const updated = page.querySelector('#unicart');
        if (!updated) {
            const wrapper = document.querySelector('.unicheckout__wrapper');
            if (wrapper) wrapper.style.display = 'none';
            const notice = document.createElement('div');
            notice.className = 'alert alert-info';
            notice.dataset.cartEmpty = '1';
            notice.textContent = 'Корзина пуста. Добавьте товар, чтобы оформить заказ.';
            document.querySelector('#content .uni-wrapper')?.prepend(notice);
            return;
        }
        const current = document.querySelector('#unicart');
        current.replaceWith(updated);
        const wrapper = document.querySelector('.unicheckout__wrapper');
        if (wrapper) wrapper.style.display = '';
        document.querySelector('[data-cart-empty]')?.remove();
        const confirm = document.querySelector('#confirm_checkout');
        if (confirm) delete confirm.dataset.cartError;
        for (const selector of ['.product-total .unicheckout-sticky__total-text', '.sub_total .unicheckout-sticky__total-text', '.total .unicheckout-sticky__total-text']) {
            const target = document.querySelector(selector);
            const source = page.querySelector(selector);
            if (target && source) target.textContent = source.textContent;
        }
        window.dispatchEvent(new CustomEvent('cart-updated', {detail: {pickupOnly: updated.dataset.pickupOnly === '1'}}));
    },

    update({count, products, html}) {
        this.count = count;
        this.products = products;
        this.html = html;
    },
};
