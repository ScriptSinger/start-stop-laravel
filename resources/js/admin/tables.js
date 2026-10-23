/*
 * Админка: таблицы с атрибутом data-resizable. Ширину колонок меняют
 * перетаскиванием границы в шапке; начальные ширины в процентах можно задать
 * атрибутом data-widths="20,60,20". Текст ячейки, который не поместился,
 * при наведении показывается подсказкой (title).
 */
const MIN_COLUMN_WIDTH = 48;

function applyInitialWidths(table, headers) {
    const widths = (table.dataset.widths || '').split(',').map(Number);

    if (widths.length !== headers.length || widths.some((width) => !(width > 0))) {
        return;
    }

    headers.forEach((header, index) => {
        header.style.width = `${widths[index]}%`;
    });
}

function freezeWidths(headers) {
    headers.forEach((header) => {
        header.style.width = `${header.getBoundingClientRect().width}px`;
    });
}

function addResizer(header, headers) {
    const handle = document.createElement('span');
    handle.className = 'col-resizer';
    header.append(handle);

    handle.addEventListener('pointerdown', (event) => {
        event.preventDefault();
        handle.setPointerCapture(event.pointerId);
        handle.classList.add('is-active');
        freezeWidths(headers);

        const startX = event.clientX;
        const startWidth = header.getBoundingClientRect().width;

        const move = (moveEvent) => {
            header.style.width = `${Math.max(MIN_COLUMN_WIDTH, startWidth + moveEvent.clientX - startX)}px`;
        };

        const stop = () => {
            handle.classList.remove('is-active');
            handle.removeEventListener('pointermove', move);
            handle.removeEventListener('pointerup', stop);
            handle.removeEventListener('pointercancel', stop);
        };

        handle.addEventListener('pointermove', move);
        handle.addEventListener('pointerup', stop);
        handle.addEventListener('pointercancel', stop);
    });
}

function showFullTextOnHover(table) {
    table.addEventListener('mouseover', (event) => {
        const cell = event.target.closest('td, th');

        if (!cell || !table.contains(cell)) {
            return;
        }

        const content = cell.firstElementChild || cell;
        const truncated = content.scrollWidth > content.clientWidth;

        if (truncated) {
            cell.title = cell.textContent.trim();
        } else {
            cell.removeAttribute('title');
        }
    });
}

function enhance(table) {
    if (table.dataset.resizableReady) {
        return;
    }

    table.dataset.resizableReady = '1';

    const headers = [...table.querySelectorAll('thead th')];

    applyInitialWidths(table, headers);
    headers.slice(0, -1).forEach((header) => addResizer(header, headers));
    showFullTextOnHover(table);
}

function enhanceAll() {
    document.querySelectorAll('table[data-resizable]').forEach(enhance);
}

document.addEventListener('DOMContentLoaded', enhanceAll);
new MutationObserver(enhanceAll).observe(document.documentElement, {childList: true, subtree: true});
