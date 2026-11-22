/*
 * Карусель на CSS scroll-snap: прокрутку, свайп и инерцию делает браузер,
 * компонент добавляет стрелки, точки, число карточек по ширине и автопрокрутку.
 * Разметка: корень x-data="scrollCarousel({...})", внутри x-ref="track" со слайдами.
 *
 * Параметры: autoplay — период автопрокрутки, мс (0 — выключена; по кругу, на паузе
 * при наведении и фокусе); items — {ширина: число карточек} по ширине блока.
 */
export default ({autoplay = 0, items = null} = {}) => ({
    page: 0,
    pages: 1,
    canPrev: false,
    canNext: false,
    paused: false,

    init() {
        const track = this.$refs.track;
        const update = () => this.update();

        track.addEventListener('scroll', update, {passive: true});
        new ResizeObserver(() => {
            this.applyItems();
            update();
        }).observe(track);

        if (autoplay > 0 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setInterval(() => {
                if (!this.paused && document.visibilityState === 'visible') this.next(true);
            }, autoplay);
        }
    },

    // Сколько карточек в ряду при текущей ширине блока (как responsive у Owl).
    applyItems() {
        if (!items) return;

        const width = this.$el.clientWidth + 20;
        const match = Object.keys(items).map(Number).filter((breakpoint) => breakpoint <= width).pop();

        this.$refs.track.style.setProperty('--items', items[match ?? Object.keys(items)[0]]);
    },

    update() {
        const track = this.$refs.track;
        const max = track.scrollWidth - track.clientWidth;

        this.canPrev = track.scrollLeft > 2;
        this.canNext = track.scrollLeft < max - 2;
        this.pages = Math.max(1, Math.ceil(track.scrollWidth / Math.max(track.clientWidth, 1) - 0.05));
        this.page = this.canNext ? Math.round(track.scrollLeft / Math.max(track.clientWidth, 1)) : this.pages - 1;
    },

    next(loop = false) {
        const track = this.$refs.track;

        if (loop && !this.canNext) {
            track.scrollTo({left: 0, behavior: 'smooth'});
        } else {
            track.scrollBy({left: track.clientWidth, behavior: 'smooth'});
        }
    },

    prev() {
        this.$refs.track.scrollBy({left: -this.$refs.track.clientWidth, behavior: 'smooth'});
    },

    go(page) {
        this.$refs.track.scrollTo({left: page * this.$refs.track.clientWidth, behavior: 'smooth'});
    },
});
