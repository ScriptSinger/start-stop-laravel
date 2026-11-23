/*
 * Окно с формой (заказ звонка, быстрый заказ, вопрос о товаре) — разметка
 * uniModalWindow темы. Без JavaScript ссылки ведут на страницы с теми же формами.
 */
export default {
    title: '',
    html: '',
    loading: false,
    error: false,
    requestId: 0,
    lastUrl: '',

    init() {
        // Закрыли окно — устаревший ответ уже не должен его заполнять.
        document.getElementById('modal-form')?.addEventListener('close', () => {
            this.requestId++;
            this.loading = false;
        });
    },

    async open(url, title) {
        const requestId = ++this.requestId;
        this.lastUrl = url;
        this.title = title;
        this.html = '';
        this.loading = true;
        this.error = false;
        const dialog = document.getElementById('modal-form');

        if (!dialog.open) dialog.showModal();

        try {
            const response = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            if (!response.ok) throw new Error('HTTP error');
            const html = await response.text();
            if (requestId === this.requestId) this.html = html;
        } catch {
            if (requestId === this.requestId) this.error = true;
        } finally {
            if (requestId === this.requestId) this.loading = false;
        }
    },
};
