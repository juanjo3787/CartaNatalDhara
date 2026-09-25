const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('dismiss hides immediately, rejects stale polls and restores a failed dismissal', async () => {
    class Element {
        constructor(tag) { this.tag = tag; this.children = []; this.style = {}; this.attributes = {}; }
        append(child) { this.children.push(child); child.parent = this; }
        replaceChildren() { this.children = []; }
        setAttribute(key, value) { this.attributes[key] = value; }
        remove() { this.parent.children = this.parent.children.filter(child => child !== this); }
    }
    const panel = new Element('aside');
    let ready;
    let timer;
    let resolveDismiss;
    let fail = false;
    const job = {job_id: 3, report_id: 4, status: 'completed', message: 'Informe disponible', progress: 100, status_url: '/reports/jobs/3', report_url: '/charts/4/report', pdf_url: '/pdf'};
    const source = fs.readFileSync(require('node:path').join(__dirname, '../../resources/views/reports/jobs-progress.blade.php'), 'utf8')
        .split('<script>')[1].split('</script>')[0]
        .replace("@json(route('reports.jobs.index'))", "'/reports/jobs'");
    const context = {
        document: {
            addEventListener: (_, fn) => ready = fn,
            getElementById: () => panel,
            querySelector: () => ({content: 'csrf-fixture'}),
            querySelectorAll: () => [],
            createElement: tag => new Element(tag),
        },
        window: {},
        setTimeout: fn => timer = fn,
        fetch: async (url, options) => {
            if (url.endsWith('/dismiss')) {
                assert.equal(options.method, 'POST');
                assert.equal(options.headers['X-CSRF-TOKEN'], 'csrf-fixture');
                return new Promise(resolve => resolveDismiss = () => resolve({ok: !fail}));
            }
            return {ok: true, json: async () => [job]};
        },
    };
    vm.runInNewContext(source, context);
    ready();
    await new Promise(setImmediate);
    let row = panel.children[0];
    let close = row.children.find(child => child.tag === 'button');
    assert.equal(close.attributes['aria-label'], 'Cerrar aviso de Carta 4');
    assert.equal(close.type, 'button');
    fail = true;
    let dismiss = close.onclick();
    assert.equal(row.hidden, true);
    resolveDismiss();
    await dismiss;
    assert.equal(row.hidden, false);
    fail = false;
    dismiss = close.onclick();
    resolveDismiss();
    await dismiss;
    assert.equal(panel.children.includes(row), false);
    await timer();
    assert.equal(panel.children.includes(row), false);
    assert.equal(panel.children.some(child => child.children.some(item => item.textContent === 'Abrir informe')), false);
});
