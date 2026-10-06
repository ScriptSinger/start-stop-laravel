/*
 * Цена, которая пересчитывается на лету (live-price.js темы UniShop2):
 * отметили трейд-ин — цена уменьшается на скидку, сменили количество на
 * странице товара — умножается; новое значение «прокручивается» за 100 мс.
 * Скидка трейд-ина, как в теме, вычитается и из старой, и из акционной цены.
 *
 * Количество приходит событием quantity-changed от qtySwitch.
 */
const ANIMATION_MS = 100;

export default ({price, special = null, tradeInDiscount = 0}) => ({
    tradeIn: false,
    quantity: 1,
    shownPrice: price,
    shownSpecial: special,

    init() {
        this.$watch('tradeIn', () => this.update());
        this.$watch('quantity', () => this.update());
    },

    update() {
        const discount = this.tradeIn ? tradeInDiscount : 0;

        this.animate('shownPrice', (price - discount) * this.quantity);

        if (special !== null) {
            this.animate('shownSpecial', (special - discount) * this.quantity);
        }
    },

    animate(property, target) {
        const from = this[property];
        const startedAt = performance.now();

        const step = (now) => {
            const progress = Math.min((now - startedAt) / ANIMATION_MS, 1);

            this[property] = from + (target - from) * progress;

            if (progress < 1) {
                requestAnimationFrame(step);
            }
        };

        requestAnimationFrame(step);
    },

    /** Формат цен старого сайта: «4400р.». */
    format(value) {
        return `${Math.round(value)}р.`;
    },
});
