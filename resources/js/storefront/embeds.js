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

/**
 * Онлайн-чат JivoSite (<meta name="jivo-widget">). Скрипт тяжёлый, поэтому,
 * как на старом сайте, не мешает загрузке страницы: подключается через 5 секунд
 * или раньше — при первом действии посетителя.
 */
export function initJivoChat() {
    const widgetId = document.querySelector('meta[name="jivo-widget"]')?.content;

    if (!widgetId) {
        return;
    }

    const events = ['scroll', 'pointerdown', 'keydown', 'touchstart'];
    let timer;

    const load = () => {
        clearTimeout(timer);
        events.forEach((event) => window.removeEventListener(event, load));
        loadScript(`https://code.jivo.ru/widget/${widgetId}`).catch(() => {});
    };

    timer = setTimeout(load, 5000);
    events.forEach((event) => window.addEventListener(event, load, {once: true, passive: true}));
}
