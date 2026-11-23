/*
 * «Мой авто»: последняя машина, для которой покупатель смотрел аккумуляторы.
 * Хранится только в браузере (localStorage); без него блок просто не показывается.
 */
const KEY = 'garage-car';

export default {
    car: null,

    init() {
        try {
            const saved = JSON.parse(localStorage.getItem(KEY));

            if (saved?.name && saved?.url) {
                this.car = saved;
            }
        } catch {
            this.car = null;
        }
    },

    save(name, url) {
        this.car = {name, url};

        try {
            localStorage.setItem(KEY, JSON.stringify(this.car));
        } catch {
            // Приватный режим или запрет хранения — работаем без запоминания.
        }
    },

    clear() {
        this.car = null;

        try {
            localStorage.removeItem(KEY);
        } catch {
            // см. save()
        }
    },
};
