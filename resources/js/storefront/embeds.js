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

let mapsPromise;

function loadMaps(key) {
    if (!mapsPromise) {
        const params = new URLSearchParams({lang: 'ru_RU'});
        if (key) {
            params.set('apikey', key);
        }
        mapsPromise = loadScript(`https://api-maps.yandex.ru/2.1/?${params}`, document.head)
            .then(() => new Promise((resolve, reject) => window.ymaps.ready(() => resolve(window.ymaps), reject)));
    }
    return mapsPromise;
}

/** Карта загружается при приближении к видимой области страницы. */
export function initYandexMaps() {
    document.querySelectorAll('[data-yandex-map]').forEach((container) => {
        const mount = async () => {
            try {
                const ymaps = await loadMaps(container.dataset.mapKey);
                const coordinates = JSON.parse(container.dataset.coordinates);
                const map = new ymaps.Map(container, {
                    center: coordinates,
                    zoom: 17,
                    controls: ['zoomControl'],
                });
                map.geoObjects.add(new ymaps.Placemark(coordinates, {
                    hintContent: 'Старт-Стоп',
                }, {preset: 'islands#redIcon'}));
                map.behaviors.disable('scrollZoom');
                const resizeObserver = new ResizeObserver(() => map.container.fitToViewport());
                resizeObserver.observe(container);
            } catch {
                container.textContent = 'Карта временно недоступна. Адрес и контакты указаны рядом.';
            }
        };
        const observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                observer.disconnect();
                mount();
            }
        }, {rootMargin: '400px 0px'});
        observer.observe(container);
    });
}

/** Счётчик Яндекс.Метрики (<meta name="metrika-counter">): вебвизор, карта кликов, электронная коммерция. */
export function initMetrika() {
    const counterId = Number(document.querySelector('meta[name="metrika-counter"]')?.content);

    if (!counterId) {
        return;
    }

    window.dataLayer = window.dataLayer || [];
    window.ym = window.ym || function () {
        (window.ym.a = window.ym.a || []).push(arguments);
    };
    window.ym.l = Date.now();

    loadScript(`https://mc.yandex.ru/metrika/tag.js?id=${counterId}`, document.head).catch(() => {});

    window.ym(counterId, 'init', {
        ssr: true,
        webvisor: true,
        clickmap: true,
        ecommerce: 'dataLayer',
        referrer: document.referrer,
        url: location.href,
        accurateTrackBounce: true,
        trackLinks: true,
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
