/*
 * Поля поиска (шапка, телефон, прилипающая шапка, страница поиска)
 * заполняются одинаково, крестик очищает все — clearBtn темы.
 */
export default {
    query: '',

    init() {
        this.query = document.querySelector('input[name="search"]')?.getAttribute('value') ?? '';
    },
};
