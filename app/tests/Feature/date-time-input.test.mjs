import { test } from 'node:test';
import assert from 'node:assert/strict';
import { bindDateTimeInput, formatDateInput, formatTimeInput, validateDateInput, validateTimeInput } from '../../resources/js/date-time-input.js';

test('dates format partial typing and normalize pasted separators', () => {
    const cases = { '1': '1', '12': '12', '120': '12/0', '1205': '12/05', '12051986': '12/05/1986', '12/05/1986': '12/05/1986', '12-05-1986': '12/05/1986', '25091986': '25/09/1986', '25//09//1986': '25/09/1986', '25091986999': '25/09/1986' };
    for (const [input, expected] of Object.entries(cases)) assert.equal(formatDateInput(input), expected);
});

test('times format partial typing and normalize pasted separators', () => {
    const cases = { '1': '1', '12': '12', '123': '12:3', '1230': '12:30', '12:30': '12:30', '12-30': '12:30', '21.30': '21:30', '21::30': '21:30', '2130999': '21:30' };
    for (const [input, expected] of Object.entries(cases)) assert.equal(formatTimeInput(input), expected);
});

test('calendar validation rejects impossible dates without correcting them', () => {
    for (const value of ['25/09/1986', '29/02/2000', '29/02/2024', '01/01/0001']) assert.equal(validateDateInput(value), true, value);
    for (const value of ['', '25/0', '32/01/1986', '15/15/1986', '31/02/1986', '29/02/1900', '01/01/0000']) assert.equal(validateDateInput(value), false, value);
    assert.equal(formatDateInput('31021986'), '31/02/1986');
});

test('24-hour validation rejects incomplete and out of range values', () => {
    for (const value of ['00:00', '09:05', '14:30', '23:59']) assert.equal(validateTimeInput(value), true, value);
    for (const value of ['', '12:3', '24:00', '12:75', '99:99']) assert.equal(validateTimeInput(value), false, value);
});

function field(kind) {
    const listeners = {};
    const input = {
        value: '', selectionStart: 0, selectionEnd: 0, error: '',
        addEventListener: (name, callback) => listeners[name] = callback,
        setCustomValidity(message) { this.error = message; },
        setSelectionRange(start, end) { this.selectionStart = start; this.selectionEnd = end; },
    };
    bindDateTimeInput(input, kind);
    const edit = (text = '', inputType = 'insertText') => {
        listeners.beforeinput({ inputType });
        let start = input.selectionStart;
        let end = input.selectionEnd;
        if (start === end && inputType === 'deleteContentBackward') start = Math.max(0, start - 1);
        if (start === end && inputType === 'deleteContentForward') end++;
        input.value = input.value.slice(0, start) + text + input.value.slice(end);
        input.setSelectionRange(start + text.length, start + text.length);
        listeners.input();
    };
    return { input, listeners, edit };
}

test('typing and deleting preserve progressive values and caret position', () => {
    for (const [kind, raw, expected] of [
        ['date', '25091986', ['2', '25', '25/0', '25/09', '25/09/1', '25/09/19', '25/09/198', '25/09/1986']],
        ['time', '2130', ['2', '21', '21:3', '21:30']],
    ]) {
        const { input, edit } = field(kind);
        [...raw].forEach((digit, index) => { edit(digit); assert.equal(input.value, expected[index]); assert.equal(input.selectionStart, input.value.length); });
        for (const value of [...expected.slice(0, -1).reverse(), '']) { edit('', 'deleteContentBackward'); assert.equal(input.value, value); }
    }
});

test('pasting, replacing digits and deleting either side of a separator are usable', () => {
    const { input, listeners, edit } = field('date');
    let prevented = false;
    listeners.paste({ clipboardData: { getData: () => '25//09//1986' }, preventDefault: () => prevented = true });
    assert.equal(prevented, true);
    assert.equal(input.value, '25/09/1986');
    input.setSelectionRange(3, 5);
    edit('1'); edit('2');
    assert.equal(input.value, '25/12/1986');
    input.setSelectionRange(2, 2);
    edit('', 'deleteContentForward');
    assert.equal(input.value, '25/21/986');
    assert.equal(input.selectionStart, 2);
    input.setSelectionRange(3, 3);
    edit('', 'deleteContentBackward');
    assert.equal(input.value, '22/19/86');
    assert.equal(input.selectionStart, 1);
});

test('custom validation waits for completion or blur, and clears during correction', () => {
    const { input, listeners, edit } = field('date');
    edit('25/0'); assert.equal(input.error, '');
    listeners.blur(); assert.notEqual(input.error, '');
    edit('9'); assert.equal(input.error, '');
    edit('1986'); assert.equal(input.error, '');
    input.setSelectionRange(0, input.value.length);
    edit('31021986'); assert.notEqual(input.error, '');
});
