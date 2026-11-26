/*
 * Форма в окне (заказ звонка, быстрый заказ, вопрос о товаре, вход):
 * отправка без перезагрузки — успех показывается вместо формы (или, с
 * reload, страница обновляется — например, после входа), ошибки — под полями.
 * Формы с целью Метрики также отправляются без перезагрузки на отдельных
 * страницах; остальные формы сохраняют обычную отправку вне окна.
 */
import { reachGoal } from '../embeds';
import { postJson } from '../utils';

export default ({reload = false} = {}) => ({
    errors: {},
    message: '',
    sending: false,

    async submit(event) {
        if (!this.$el.closest('dialog') && !this.$el.dataset.metrikaGoal) {
            return;
        }

        event.preventDefault();
        this.sending = true;
        this.errors = {};

        try {
            const json = await postJson(this.$el.action, new FormData(this.$el));

            if (reload) {
                location.reload();

                return;
            }

            this.message = json.message;

            // Цель Метрики: data-metrika-goal на форме (callback, quick_order, product_question).
            if (this.$el.dataset.metrikaGoal) {
                reachGoal(this.$el.dataset.metrikaGoal);
            }
        } catch (error) {
            this.errors = error.data?.errors
                ?? {_form: [error.data?.message || 'Не получилось отправить. Попробуйте ещё раз или позвоните нам.']};
        } finally {
            this.sending = false;
        }
    },

    error(field) {
        return this.errors[field]?.[0] ?? '';
    },
});
