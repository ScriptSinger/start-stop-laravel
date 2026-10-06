/*
 * Оформление заказа: вкладки «Самовывоз / Доставка» задают способ получения,
 * оплата картой — только при самовывозе. Сервер проверяет то же самое
 * (CheckoutRequest), здесь — только удобство.
 */
export default ({delivery, payment, pickup, cardOnlyPayments}) => ({
    delivery,
    payment,

    init() {
        this.$watch('delivery', () => {
            if (!this.isPaymentAllowed(this.payment)) {
                this.payment = this.$el.querySelector('input[name=payment]:not(:disabled)')?.value ?? this.payment;
            }
        });
    },

    isPaymentAllowed(method) {
        return this.delivery === pickup || !cardOnlyPayments.includes(method);
    },

    /** Поле с ошибкой подсвечено, пока его не исправят (form_error темы). */
    clearWarning(event) {
        event.target.classList.remove('input-warning');
    },
});
