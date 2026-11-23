/*
 * «Поделиться» на странице товара: кнопки <button data-social="…"> внутри #goodshare.
 * Делимся канонической ссылкой без utm- и yclid-меток: иначе друг, открывший ссылку,
 * попадёт в статистику как рекламный визит. На телефонах вместо значков показывается
 * одна кнопка системного меню «Поделиться» (navigator.share).
 */
const shareUrls = {
    vkontakte: (url, title) => `https://vk.com/share.php?url=${url}&title=${title}`,
    telegram: (url, title) => `https://t.me/share/url?url=${url}&text=${title}`,
    whatsapp: (url, title) => `https://wa.me/?text=${title}%20${url}`,
};

const statusResetDelay = 2500;

function canonicalUrl() {
    const canonical = document.querySelector('link[rel="canonical"]')?.href;

    return canonical || `${location.origin}${location.pathname}`;
}

function showStatus(group, text) {
    const status = group.querySelector('[data-share-status]');

    if (!status) {
        return;
    }

    status.textContent = text;
    window.clearTimeout(status.resetTimer);
    status.resetTimer = window.setTimeout(() => {
        status.textContent = '';
    }, statusResetDelay);
}

async function copyLink(group, url) {
    try {
        await navigator.clipboard.writeText(url);
        showStatus(group, 'Ссылка скопирована');
    } catch {
        window.prompt('Скопируйте ссылку', url);
    }
}

function enableNativeShare() {
    const group = document.getElementById('goodshare');

    if (group && navigator.share && window.matchMedia('(pointer: coarse)').matches) {
        group.dataset.native = '';
    }
}

export function initShare() {
    enableNativeShare();

    document.addEventListener('click', (event) => {
        const button = event.target.closest('#goodshare [data-social]');

        if (!button) {
            return;
        }

        const group = button.closest('#goodshare');
        const network = button.dataset.social;
        const rawUrl = canonicalUrl();
        const rawTitle = document.title;

        if (network === 'copy') {
            copyLink(group, rawUrl);

            return;
        }

        if (network === 'native') {
            navigator.share({title: rawTitle, url: rawUrl}).catch(() => {});

            return;
        }

        const shareUrl = shareUrls[network];

        if (!shareUrl) {
            return;
        }

        window.open(
            shareUrl(encodeURIComponent(rawUrl), encodeURIComponent(rawTitle)),
            'share',
            'width=640,height=480,resizable=yes,scrollbars=yes',
        );
    });
}
