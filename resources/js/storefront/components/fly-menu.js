/*
 * Шапка, прилипающая при прокрутке (uniFlyMenu из fly-menu-cart.js темы).
 * На телефоне значки раскрывают меню и поиск. Разметка — partials/fly-menu.
 */
export default () => ({
    shown: false,
    open: null,

    onScroll() {
        this.shown = window.scrollY > 200;

        if (!this.shown) {
            this.close();
        }
    },

    toggle(block) {
        this.open = this.open === block ? null : block;
        document.body.classList.toggle('scroll-disabled', this.open === 'search');

        if (this.open === 'menu') {
            document.querySelector('.menu-open')?.click();
        }

        if (this.open === 'search') {
            this.$nextTick(() => this.$el.querySelector('.fly-menu__search-m input[name=search]')?.focus());
        }
    },

    close() {
        // Блокировку прокрутки снимаем, только если её поставил поиск: открытое
        // мобильное меню категорий ставит её само, и клик по нему — «снаружи».
        if (this.open === 'search') {
            document.body.classList.remove('scroll-disabled');
        }

        this.open = null;
    },
});
