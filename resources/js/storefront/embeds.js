/*
 * Сторонние виджеты, перенесённые со старого сайта: подключаются только на
 * страницах, где есть их блок.
 */

function loadScript(src, parent = document.body) {
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');

        script.src = src;
        script.async = true;
        script.charset = 'utf-8';
        script.onload = resolve;
        script.onerror = reject;
        parent.appendChild(script);
    });
}

/** Отзывы MyReviews: <iframe data-reviews-widget data-script data-uuid data-name>. */
export function initReviewsWidget() {
    const frame = document.querySelector('[data-reviews-widget]');

    if (!frame) {
        return;
    }

    const {script, uuid, name} = frame.dataset;

    loadScript(script)
        .then(() => new window.myReviews.BlockWidget({uuid, name, additionalFrame: 'none', lang: 'ru', widgetId: '0'}).init())
        .catch(() => {});
}

/** Карта из конструктора Яндекс.Карт: скрипт рисует карту там, где он вставлен. */
export function initYandexMaps() {
    document.querySelectorAll('[data-yandex-map]').forEach((container) => {
        loadScript(container.dataset.yandexMap, container).catch(() => {});
    });
}
