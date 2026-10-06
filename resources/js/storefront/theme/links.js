/*
 * Блоки-ссылки темы: <div class="uni-href" data-href="…" data-target="_blank">.
 */
export function initUniHref() {
    document.addEventListener('click', (event) => {
        const block = event.target.closest('.uni-href');
        const href = block?.dataset.href;

        if (!href) {
            return;
        }

        if ((block.getAttribute('target') || block.dataset.target) === '_blank') {
            window.open(href, '_blank');
        } else {
            location.href = href;
        }
    });
}

/** Длинные хлебные крошки на телефоне прокручены к концу, как в теме. */
export function scrollBreadcrumbs() {
    document.querySelectorAll('.breadcrumb').forEach((breadcrumb) => {
        breadcrumb.scrollLeft = breadcrumb.scrollWidth;
    });
}
