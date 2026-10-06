/*
 * «Поделиться» на странице товара — как goodshare.js в теме старого сайта:
 * кнопки <div data-social="vkontakte|telegram"> открывают окно соцсети.
 */
const shareUrls = {
    vkontakte: (url, title) => `https://vk.com/share.php?url=${url}&title=${title}`,
    telegram: (url, title) => `https://t.me/share/url?url=${url}&text=${title}`,
};

export function initShare() {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-social]');
        const shareUrl = shareUrls[button?.dataset.social];

        if (!shareUrl) {
            return;
        }

        const url = encodeURIComponent(location.href);
        const title = encodeURIComponent(document.title);

        window.open(shareUrl(url, title), 'share', 'width=640,height=480,resizable=yes,scrollbars=yes');
    });
}
