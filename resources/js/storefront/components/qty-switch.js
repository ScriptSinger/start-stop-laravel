/*
 * Количество с кнопками «+» и «−» (qty-switch темы). С autosubmit форма
 * отправляется сразу — в корзине и окне «Корзина». Новое количество
 * сообщается событием quantity-changed (его слушает livePrice).
 */
export default ({value = 1, min = 1, max = 99, autosubmit = false} = {}) => ({
    value,

    step(delta) {
        this.set(this.value + delta);
    },

    set(raw) {
        const number = parseInt(raw, 10);
        const next = Math.min(Math.max(Number.isNaN(number) ? min : number, min), max);
        const changed = next !== this.value;

        this.value = next;

        if (changed) {
            this.$dispatch('quantity-changed', next);
        }

        if (autosubmit && changed) {
            this.$nextTick(() => this.$el.closest('form').requestSubmit());
        }
    },
});
