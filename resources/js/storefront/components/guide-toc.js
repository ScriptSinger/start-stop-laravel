/*
 * Оглавление гайда (resources/views/guide.blade.php): подсвечивает раздел,
 * который сейчас читают. Без JavaScript оглавление остаётся обычными якорями.
 */
export default () => ({
    active: '',

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
