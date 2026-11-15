/*
 * Окно с формой (заказ звонка, быстрый заказ, вопрос о товаре) — разметка
 * uniModalWindow темы. Без JavaScript ссылки ведут на страницы с теми же формами.
 */
import $ from 'jquery';

export default {
    title: '',
    html: '',
    loading: false,
    error: false,
    requestId: 0,
    opener: null,
    lastUrl: '',

    init() {
        $('#modal-form, #modal-cart').on('keydown', function (event) {
            if (event.key !== 'Tab') return;

            const elements = [...this.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')]
                .filter((element) => element.getClientRects().length && !element.closest('[inert]'));
            if (!elements.length) {
                event.preventDefault();
                this.focus();
                return;
            }

            const first = elements[0];
            const last = elements[elements.length - 1];
            if (event.shiftKey && (document.activeElement === first || document.activeElement === this)) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
        $('#modal-form').on('hide.bs.modal', () => {
            this.requestId++;
            this.loading = false;
        }).on('hidden.bs.modal', () => {
            this.opener?.focus();
            this.opener = null;
        });
    },

    async open(url, title) {
        const requestId = ++this.requestId;
        if (!$('#modal-form').hasClass('in')) this.opener = document.activeElement;
        this.lastUrl = url;
        this.title = title;
        this.html = '';
        this.loading = true;
        this.error = false;
        $('#modal-form').modal('show');

        try {
            const response = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            if (!response.ok) throw new Error('HTTP error');
            const html = await response.text();
            if (requestId === this.requestId) this.html = html;
        } catch {
            if (requestId === this.requestId) this.error = true;
        } finally {
            if (requestId === this.requestId) this.loading = false;
        }
    },
};
