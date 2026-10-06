/*
 * Всплывающее уведомление (uniFlyAlert темы): одно за раз, 5 секунд —
 * как в настройках старого сайта.
 */
export default {
    current: null,
    timer: null,

    show(type, message) {
        clearTimeout(this.timer);
        this.current = {type, message};
        this.timer = setTimeout(() => this.hide(), 5000);
    },

    hide() {
        this.current = null;
    },
};
