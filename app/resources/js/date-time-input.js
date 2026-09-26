const digits = value => value.replace(/\D/g, '');

function format(value, lengths, separator) {
    const raw = digits(value).slice(0, lengths.reduce((sum, length) => sum + length, 0));
    let offset = 0;
    return lengths.map(length => {
        const part = raw.slice(offset, offset + length);
        offset += length;
        return part;
    }).filter(Boolean).join(separator);
}

export const formatDateInput = value => format(value, [2, 2, 4], '/');
export const formatTimeInput = value => format(value, [2, 2], ':');

export function validateDateInput(value) {
    if (!/^\d{2}\/\d{2}\/\d{4}$/.test(value)) return false;
    const [day, month, year] = value.split('/').map(Number);
    const leap = year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0);
    const days = [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    return year > 0 && month >= 1 && month <= 12 && day >= 1 && day <= days[month - 1];
}

export const validateTimeInput = value => /^(?:[01]\d|2[0-3]):[0-5]\d$/.test(value);

export function bindDateTimeInput(input, kind) {
    const isDate = kind === 'date';
    const formatter = isDate ? formatDateInput : formatTimeInput;
    const validator = isDate ? validateDateInput : validateTimeInput;
    const size = isDate ? 10 : 5;
    const validate = (completeOnly = false) => {
        const invalid = input.value && (!completeOnly || input.value.length === size) && !validator(input.value);
        input.setCustomValidity(invalid ? (isDate ? 'Introduce una fecha real en formato DD/MM/AAAA.' : 'Introduce una hora entre 00:00 y 23:59.') : '');
    };
    const normalize = () => {
        const raw = input.value;
        const caret = input.selectionStart ?? raw.length;
        const count = digits(raw.slice(0, caret)).length;
        input.value = formatter(raw);
        let position = 0;
        let seen = 0;
        while (position < input.value.length && seen < count) {
            if (/\d/.test(input.value[position])) seen++;
            position++;
        }
        if (/\D/.test(input.value[position] || '') && /\D/.test(raw[caret - 1] || '')) position++;
        input.setSelectionRange(position, position);
        validate(true);
    };
    input.addEventListener('beforeinput', event => {
        const start = input.selectionStart;
        if (start !== input.selectionEnd) return;
        const backward = event.inputType === 'deleteContentBackward';
        const forward = event.inputType === 'deleteContentForward';
        const index = backward ? start - 1 : start;
        if ((backward || forward) && /[/:]/.test(input.value[index] || '')) {
            // Delete the neighbouring digit with its separator, so formatting cannot trap the caret.
            input.setSelectionRange(backward ? Math.max(0, index - 1) : index, backward ? start : index + 2);
        }
    });
    input.addEventListener('input', normalize);
    input.addEventListener('paste', event => {
        if (!event.clipboardData) return;
        event.preventDefault();
        const start = input.selectionStart ?? 0;
        const end = input.selectionEnd ?? start;
        const inserted = digits(event.clipboardData.getData('text'));
        input.value = input.value.slice(0, start) + inserted + input.value.slice(end);
        input.setSelectionRange(start + inserted.length, start + inserted.length);
        normalize();
    });
    input.addEventListener('blur', () => validate());
    input.value = formatter(input.value);
    validate(true);
}

export function initializeDateTimeInputs(root = document) {
    root.querySelectorAll('[data-date-time]').forEach(input => bindDateTimeInput(input, input.dataset.dateTime));
}
