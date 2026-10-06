/*
 * Мелкие помощники витрины.
 */

export function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'})[char]);
}

export function csrfToken() {
    return document.querySelector('meta[name=csrf-token]')?.content ?? '';
}

/**
 * POST формы или данных с ответом JSON. Ошибка HTTP — исключение с полем
 * response (для ошибок валидации 422).
 */
export async function postJson(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest'},
        body: body instanceof FormData ? body : new URLSearchParams(body ?? {}),
    });

    if (!response.ok) {
        const error = new Error(`HTTP ${response.status}`);
        error.response = response;
        error.data = await response.json().catch(() => ({}));
        throw error;
    }

    return response.json();
}

export function scrollToElement(target, offset = 150) {
    const element = typeof target === 'string' ? document.querySelector(target) : target;

    if (element) {
        window.scrollTo({top: element.getBoundingClientRect().top + window.scrollY - offset, behavior: 'smooth'});
    }
}
