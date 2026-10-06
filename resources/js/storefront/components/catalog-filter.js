/*
 * Фильтр каталога — как OCFilter старого сайта.
 *  - Отметка не перезагружает страницу: сервер считает, сколько будет товаров
 *    (CategoryController::count), фильтр применяется кнопкой «Показать N товаров».
 *    Кнопка с «Сбросить» прилипает к низу экрана, а на компьютере такая же
 *    подсказка всплывает справа от только что отмеченного значения.
 *  - Цена — поля «от — до» и шкала с двумя ползунками.
 *  - На телефоне форма живёт в выезжающей панели: язычок «Фильтр» у края,
 *    список групп, нажатие на группу открывает её значения.
 */
const MOBILE = '(max-width: 767px)';

const plural = (count, [one, few, many]) => {
    const mod10 = count % 10;
    const mod100 = count % 100;

    if (mod10 === 1 && mod100 !== 11) return one;
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return few;

    return many;
};

/** Подпись деления шкалы, как у старого сайта: «16 тыс.». */
const tickLabel = (value) => (value >= 1000 ? `${Math.round(value / 1000)} тыс.` : String(Math.round(value)));

export default ({countUrl, priceMin, priceMax}) => ({
    open: false,
    group: null,
    groupTitle: '',
    changed: false,
    count: null,
    // Подписи выбранного читаются из формы; счётчик заставляет их пересчитаться.
    version: 0,
    countRequest: null,
    // Подсказка у отмеченного значения (компьютер): отступ сверху внутри формы.
    popoverTop: null,

    priceMin,
    priceMax,
    priceFrom: priceMin,
    priceTo: priceMax,

    init() {
        const form = this.$refs.form;

        this.priceFrom = Number(form.elements.price_from.value || priceMin);
        this.priceTo = Number(form.elements.price_to.value || priceMax);
    },

    get isMobile() {
        return window.matchMedia(MOBILE).matches;
    },

    get hasPriceScale() {
        return this.priceMax > this.priceMin;
    },

    get submitLabel() {
        if (!this.changed) return 'Выберите фильтры';
        if (this.count === null) return 'Считаем…';

        return `Показать ${this.count} ${plural(this.count, ['товар', 'товара', 'товаров'])}`;
    },

    /** Деления шкалы цены: пять подписей от минимума до максимума. */
    get priceTicks() {
        return [0, 1, 2, 3, 4].map((step) => tickLabel(this.priceMin + (this.priceMax - this.priceMin) * step / 4));
    },

    /** Закрашенный отрезок шкалы между ползунками, в процентах. */
    get priceRangeStyle() {
        const span = this.priceMax - this.priceMin || 1;
        const left = (this.priceFrom - this.priceMin) / span * 100;
        const right = (this.priceTo - this.priceMin) / span * 100;

        return `left: ${left}%; width: ${right - left}%`;
    },

    show() {
        this.open = true;
        document.body.classList.add('scroll-disabled');
    },

    close() {
        this.open = false;
        this.group = null;
        document.body.classList.remove('scroll-disabled');
    },

    openGroup(key, title) {
        this.group = key;
        this.groupTitle = title;
    },

    onChange(event) {
        this.anchorPopover(event.target);
        this.touch();
    },

    /** Ползунок: значения не дают друг другу пересечься. */
    slidePrice(edge, value) {
        value = Number(value);

        if (edge === 'from') this.priceFrom = Math.min(value, this.priceTo);
        else this.priceTo = Math.max(value, this.priceFrom);

        this.touch();
    },

    /** Ввели цену руками — подвинуть ползунок (в пределах цен раздела). */
    typePrice(edge, value) {
        const number = Math.min(Math.max(Number(value) || (edge === 'from' ? this.priceMin : this.priceMax), this.priceMin), this.priceMax);

        if (edge === 'from') this.priceFrom = number;
        else this.priceTo = number;

        this.touch();
    },

    touch() {
        this.changed = true;
        this.version++;
        clearTimeout(this.countRequest);
        this.count = null;
        this.countRequest = setTimeout(() => this.recount(), 250);
    },

    async recount() {
        const response = await fetch(`${countUrl}?${this.query()}`, {headers: {'Accept': 'application/json'}});

        this.count = response.ok ? (await response.json()).count : null;
    },

    /** Параметры фильтра без пустых полей и без цены, равной границам раздела. */
    query() {
        const query = new URLSearchParams();

        for (const [name, value] of new FormData(this.$refs.form)) {
            if (value === '') continue;
            if (name === 'price_from' && Number(value) <= this.priceMin) continue;
            if (name === 'price_to' && Number(value) >= this.priceMax) continue;

            query.append(name, value);
        }

        return query.toString();
    },

    apply() {
        const query = this.query();

        window.location.href = this.$refs.form.action + (query ? `?${query}` : '');
    },

    anchorPopover(input) {
        if (this.isMobile) return;

        const row = input.closest('.checkbox, .catalog-filter__body') ?? input;
        const form = this.$refs.form.getBoundingClientRect();
        const box = row.getBoundingClientRect();

        this.popoverTop = box.top - form.top + box.height / 2;
    },

    /** Что выбрано в группе — под её названием в списке групп (телефон). */
    summary(key) {
        this.version;

        if (key === 'price') {
            return this.priceFrom > this.priceMin || this.priceTo < this.priceMax ? `${this.priceFrom} – ${this.priceTo} р.` : '';
        }

        const group = this.$refs.form.querySelector(`[data-group="${key}"]`);

        if (!group) return '';

        return [...group.querySelectorAll('input[type=checkbox]:checked')].map((input) => input.dataset.label).join(', ');
    },

    clearGroup(key) {
        if (key === 'price') {
            this.priceFrom = this.priceMin;
            this.priceTo = this.priceMax;
        }

        this.$refs.form.querySelectorAll(`[data-group="${key}"] input[type=checkbox]`).forEach((input) => {
            input.checked = false;
        });

        this.touch();
    },
});
