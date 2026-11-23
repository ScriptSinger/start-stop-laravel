/*
 * Маска телефона «+7 (999) 999-99-99» для <input type="tel" x-phone-mask>.
 * Подставляется по мере ввода; вставка «8 987…», «+7987…» и «987…» даёт один результат.
 */
const PREFIX = '+7 (';

/** Только цифры номера без кода страны, не больше десяти. */
export function nationalDigits(value) {
    let digits = value.replace(/\D/g, '');

    // «+7…» — код страны уже в значении; без плюса его могут вставить как 7 или 8.
    if (value.trim().startsWith('+7') || digits[0] === '7' || digits[0] === '8') {
        digits = digits.slice(1);
    }

    return digits.slice(0, 10);
}

export function formatPhone(digits) {
    if (!digits) return '';

    const part = (from, to) => digits.slice(from, to);
    let result = PREFIX + part(0, 3);

    if (digits.length >= 3) result += ')';
    if (digits.length > 3) result += ' ' + part(3, 6);
    if (digits.length > 6) result += '-' + part(6, 8);
    if (digits.length > 8) result += '-' + part(8, 10);

    return result;
}

export default function phoneMask(el) {
    let previous = el.value ? formatPhone(nationalDigits(el.value)) : '';

    if (previous) el.value = previous;

    el.addEventListener('input', (event) => {
        let digits = nationalDigits(el.value);

        // Стёрли знак препинания — формат вернул бы его обратно, поэтому убираем цифру перед ним.
        if (event.inputType?.startsWith('delete') && formatPhone(digits) === previous) {
            digits = digits.slice(0, -1);
        }

        previous = formatPhone(digits);
        el.value = previous;
    });

    el.addEventListener('focus', () => {
        if (!el.value) el.value = PREFIX;
    });

    el.addEventListener('blur', () => {
        if (el.value === PREFIX) el.value = '';
    });
}
