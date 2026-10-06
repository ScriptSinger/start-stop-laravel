/*
 * Форма в окне (заказ звонка, быстрый заказ, вопрос о товаре): отправка без
 * перезагрузки — успех показывается вместо формы, ошибки — под полями.
 * На отдельной странице (/callback и т.п.) форма отправляется как обычно.
 */
import { postJson } from '../utils';

export default () => ({
    errors: {},
    message: '',
    sending: false,

    async submit(event) {
        if (!this.$el.closest('.modal')) {
            return;
        }

        event.preventDefault();
        this.sending = true;
        this.errors = {};

        try {
            this.message = (await postJson(this.$el.action, new FormData(this.$el))).message;
        } catch (error) {
            this.errors = error.data?.errors ?? {_form: ['Не получилось отправить. Попробуйте ещё раз или позвоните нам.']};
        } finally {
            this.sending = false;
        }
    },

    error(field) {
        return this.errors[field]?.[0] ?? '';
    },
});
