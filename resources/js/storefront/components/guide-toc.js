/*
 * Оглавление гайда (resources/views/guide.blade.php): плавно прокручивает к
 * разделу и подсвечивает тот, который сейчас читают. Без JavaScript
 * оглавление остаётся обычными якорями.
 */
export default () => ({
    active: '',

    /**
     * Плавная прокрутка к разделу, как у кнопки «наверх»: при включённом в
     * системе «уменьшении анимации» — мгновенно. Отступ под шапку задаёт
     * scroll-margin-top у заголовков (guide.css).
     */
    go(id) {
        const heading = document.getElementById(id);

        if (!heading) {
            return;
        }

        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        this.active = id;
        heading.scrollIntoView({behavior: reduceMotion ? 'auto' : 'smooth', block: 'start'});
        history.replaceState(null, '', `#${id}`);
    },

    init() {
        const headings = [...this.$root.querySelectorAll('.guide__body h2[id]')];

        if (!headings.length || !('IntersectionObserver' in window)) {
            return;
        }

        this.active = headings[0].id;

        const observer = new IntersectionObserver((entries) => {
            const visible = entries.filter((entry) => entry.isIntersecting);

            if (visible.length) {
                this.active = visible.sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0].target.id;
            }
        }, {rootMargin: '-90px 0px -65% 0px'});

        headings.forEach((heading) => observer.observe(heading));
    },
});
