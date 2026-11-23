/*
 * Подсказки адреса доставки при оформлении заказа: по мере набора под полем
 * появляется список адресов Уфы, который отдаёт наш сервер (AddressSuggestionController).
 * Выбор мышью или стрелками и Enter подставляет адрес. Если подсказки не пришли,
 * поле работает как обычное: адрес можно ввести вручную.
 */
const MIN_LENGTH = 3;

export default ({url}) => ({
    items: [],
    open: false,
    active: -1,
    controller: null,

    async search() {
        const query = this.$refs.input.value.trim();

        this.controller?.abort();

        if (query.length < MIN_LENGTH) {
            this.close();

            return;
        }

        this.controller = new AbortController();

        try {
            const response = await fetch(`${url}?${new URLSearchParams({query})}`, {
                headers: {Accept: 'application/json'},
                signal: this.controller.signal,
            });

            if (!response.ok) {
                this.close();

                return;
            }

            this.items = (await response.json()).suggestions ?? [];
            this.active = -1;
            this.open = this.items.length > 0;
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.close();
            }
        }
    },

    move(step) {
        if (!this.open) {
            return;
        }

        this.active = (this.active + step + this.items.length) % this.items.length;
    },

    /** Enter выбирает подсказку, только если она выделена; иначе форма отправляется как обычно. */
    enter(event) {
        if (this.open && this.active >= 0) {
            event.preventDefault();
            this.pick(this.items[this.active]);
        }
    },

    pick(item) {
        this.$refs.input.value = item;
        this.close();
        this.$refs.input.dispatchEvent(new Event('input', {bubbles: true}));
        this.$refs.input.focus();
    },

    close() {
        this.open = false;
        this.active = -1;
    },
});
