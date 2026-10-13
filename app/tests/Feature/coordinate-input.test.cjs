const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('manual coordinates round to six decimals without hiding invalid input', () => {
    const source = fs.readFileSync(path.join(__dirname, '../../resources/js/app.js'), 'utf8');
    const start = source.indexOf("document.addEventListener('DOMContentLoaded', () => {\n\tdocument.querySelectorAll('input[name=\"latitude\"]");
    const end = source.indexOf("\n});", start) + 4;
    const inputs = [
        { name: 'latitude', valueAsNumber: 40.4167759, value: '40.4167759' },
        { name: 'longitude', valueAsNumber: -3.7037908, value: '-3.7037908' },
        { name: 'latitude', valueAsNumber: 90.0000001, value: '90.0000001' },
        { name: 'longitude', valueAsNumber: NaN, value: '' },
    ];
    inputs.forEach(input => { input.addEventListener = (_, callback) => { input.change = callback; }; });
    vm.runInNewContext(source.slice(start, end), {
        document: { addEventListener: (_, callback) => callback(), querySelectorAll: () => inputs },
    });
    inputs.forEach(input => input.change());
    assert.deepEqual(inputs.map(input => input.value), ['40.416776', '-3.703791', '90.0000001', '']);
});
