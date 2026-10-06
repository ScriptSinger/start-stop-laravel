/*
 * «Показать еще» под списком товаров (showmore-ajaxpagination.js темы):
 * следующая страница дописывается к текущей, адрес меняется на неё.
 * Разметка — partials/product-grid.blade.php.
 */
export default (nextUrl) => ({
    nextUrl,
    loading: false,

    async load() {
        if (!this.nextUrl || this.loading) {
            return;
        }

        const url = this.nextUrl;
        this.loading = true;

        try {
            const html = await (await fetch(url)).text();
            const page = new DOMParser().parseFromString(html, 'text/html');
            const grid = page.querySelector('[data-product-grid]');

            this.$refs.products.insertAdjacentHTML('beforeend', grid.querySelector('[x-ref=products]').innerHTML);
            this.$refs.pagination.innerHTML = grid.querySelector('[x-ref=pagination]').innerHTML;
            this.$refs.paginationText.textContent = grid.querySelector('[x-ref=paginationText]').textContent;
            this.nextUrl = grid.dataset.nextUrl || null;

            window.history.pushState({}, '', url);
        } finally {
            this.loading = false;
        }
    },
});
